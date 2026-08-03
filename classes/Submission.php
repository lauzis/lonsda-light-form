<?php

namespace LonsdaLightForm;

/**
 * Validates and accepts a submitted form.
 *
 * Validation is repeated here rather than trusted from the browser: the
 * attributes the renderer emits are a convenience for the visitor, and anyone
 * can remove them before posting.
 */
class Submission
{
    /**
     * Fired once a submission has passed every check.
     *
     * Nothing in this plugin stores or emails submissions yet — this is the
     * seam that does it, so that behaviour can live in a theme or a companion
     * plugin without this one growing a mail stack.
     *
     * do_action( 'lonsda_form_submitted', array $values, array $form, array $context )
     */
    public const HOOK_SUBMITTED = 'lonsda_form_submitted';

    /**
     * Fired for a submission that failed validation.
     *
     * do_action( 'lonsda_form_rejected', array $errors, array $form, array $context )
     */
    public const HOOK_REJECTED = 'lonsda_form_rejected';

    /**
     * Lets a listener add errors of its own — an external blocklist, say.
     *
     * apply_filters( 'lonsda_form_validate', array $errors, array $values, array $form )
     */
    public const FILTER_VALIDATE = 'lonsda_form_validate';

    /** @var array{form_id:int,errors:array,values:array,notice:string}|null */
    private static $result = null;

    public static function init(): void
    {
        // template_redirect: after the query is resolved, before anything is
        // rendered, so a form can still be redisplayed with its errors.
        add_action('template_redirect', [self::class, 'maybeHandle']);
    }

    /** The outcome of this request's submission, if there was one. */
    public static function result(): ?array
    {
        return self::$result;
    }

    public static function maybeHandle(): void
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || !isset($_POST[Renderer::FIELD_FORM_ID])) {
            return;
        }

        $form_id = (int) $_POST[Renderer::FIELD_FORM_ID];
        $form    = Forms::get($form_id);

        if (!$form) {
            return;
        }

        $nonce = isset($_POST['llf_nonce']) ? sanitize_text_field(wp_unslash($_POST['llf_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'llf_submit_' . $form_id)) {
            self::$result = [
                'form_id' => $form_id,
                'errors'  => [],
                'values'  => [],
                'notice'  => __('That form had expired. Please try again.', 'lonsda-light-form'),
            ];

            Logs::add('submission', 'Rejected: the nonce did not verify.', ['form' => $form_id]);

            return;
        }

        $raw    = isset($_POST['llf']) && is_array($_POST['llf']) ? wp_unslash($_POST['llf']) : [];
        $fields = $form['settings']['fields'] ?? [];
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $name  = $field['name'];
            $given = $raw[$name] ?? null;

            if ('checkbox' === $field['type']) {
                $value = (bool) $given;
            } elseif ('textarea' === $field['type']) {
                $value = sanitize_textarea_field((string) $given);
            } else {
                $value = sanitize_text_field((string) $given);
            }

            $values[$name] = $value;
            $error         = self::validate($field, $value);

            if ('' !== $error) {
                $errors[$name] = $error;
            }
        }

        if (!self::passesSpamChecks()) {
            // Deliberately vague, and logged rather than shown: telling a bot
            // which check it tripped just helps it past the next one.
            Logs::add('submission', 'Rejected: failed a spam check.', ['form' => $form_id]);

            self::$result = [
                'form_id' => $form_id,
                'errors'  => [],
                'values'  => $values,
                'notice'  => __('Your message could not be sent. Please try again.', 'lonsda-light-form'),
            ];

            return;
        }

        if (!empty($form['settings']['recaptcha']) && !self::recaptchaPassed()) {
            $errors['_recaptcha'] = __('Please confirm you are not a robot.', 'lonsda-light-form');
        }

        /** @var array $errors */
        $errors = (array) apply_filters(self::FILTER_VALIDATE, $errors, $values, $form);

        $context = [
            'post_id' => get_the_ID(),
            'ip'      => self::clientIp(),
            'time'    => time(),
        ];

        if (!empty($errors)) {
            Logs::add('submission', 'Rejected: validation failed.', [
                'form'   => $form_id,
                'fields' => array_keys($errors),
            ]);

            do_action(self::HOOK_REJECTED, $errors, $form, $context);

            self::$result = [
                'form_id' => $form_id,
                'errors'  => $errors,
                'values'  => $values,
                'notice'  => __('Please check the highlighted fields.', 'lonsda-light-form'),
            ];

            return;
        }

        Logs::add('submission', 'Accepted.', ['form' => $form_id, 'fields' => count($values)]);

        do_action(self::HOOK_SUBMITTED, $values, $form, $context);

        self::$result = [
            'form_id' => $form_id,
            'errors'  => [],
            'values'  => [],
            'notice'  => __('Thank you — your message has been sent.', 'lonsda-light-form'),
        ];
    }

    /**
     * Checks one value against its field's rules.
     *
     * @param array $field
     * @param mixed $value
     * @return string Empty when the value is acceptable.
     */
    public static function validate(array $field, $value): string
    {
        $required = !empty($field['required']);

        if ('checkbox' === $field['type']) {
            return ($required && !$value)
                ? __('This box must be ticked.', 'lonsda-light-form')
                : '';
        }

        $value = (string) $value;

        if ('' === trim($value)) {
            // An empty optional field skips the remaining rules: an unanswered
            // question is not a badly answered one.
            return $required ? __('This field is required.', 'lonsda-light-form') : '';
        }

        if (!empty($field['max_length']) && mb_strlen($value) > (int) $field['max_length']) {
            return sprintf(
                /* translators: %d: maximum number of characters */
                __('Please use no more than %d characters.', 'lonsda-light-form'),
                (int) $field['max_length']
            );
        }

        if ('email' === ($field['validation'] ?? '') && !is_email($value)) {
            return __('Please enter a valid email address.', 'lonsda-light-form');
        }

        if ('regex' === ($field['validation'] ?? '') && '' !== (string) $field['pattern']) {
            $pattern = '/' . str_replace('/', '\/', (string) $field['pattern']) . '/u';

            // A malformed pattern is the site's mistake, not the visitor's, so
            // it is logged and the value allowed through rather than rejecting
            // everyone until someone notices.
            $matched = @preg_match($pattern, $value);

            if (false === $matched) {
                Logs::error('validation', 'A field has an invalid pattern; it was skipped.', [
                    'field'   => $field['name'],
                    'pattern' => $field['pattern'],
                ]);

                return '';
            }

            if (0 === $matched) {
                return __('Please enter this in the expected format.', 'lonsda-light-form');
            }
        }

        return '';
    }

    /** Honeypot and minimum completion time. */
    private static function passesSpamChecks(): bool
    {
        if (Settings::get('honeypot', true) && '' !== trim((string) ($_POST[Renderer::FIELD_HONEYPOT] ?? ''))) {
            return false;
        }

        $minimum = (int) Settings::get('min_seconds', 0);

        if ($minimum > 0) {
            $started = (int) ($_POST[Renderer::FIELD_STARTED] ?? 0);

            if ($started > 0 && (time() - $started) < $minimum) {
                return false;
            }
        }

        return true;
    }

    /** Verifies the reCAPTCHA response with Google. */
    private static function recaptchaPassed(): bool
    {
        $secret = trim((string) Settings::get('recaptcha_secret_key', ''));
        $token  = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';

        if ('' === $secret || '' === $token) {
            return false;
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => self::clientIp(),
            ],
        ]);

        if (is_wp_error($response)) {
            Logs::error('recaptcha', 'Could not reach Google to verify.', ['error' => $response->get_error_message()]);

            // Unreachable verification is the site's problem. Failing closed
            // would silently break every form the moment Google is slow.
            return true;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) && !empty($body['success']);
    }

    private static function clientIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
}
