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
     *
     * $context carries the metadata common to every submission — see context().
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

    /**
     * Lets a site add to, or correct, the metadata recorded with a submission.
     *
     * apply_filters( 'lonsda_form_context', array $context, int $form_id )
     */
    public const FILTER_CONTEXT = 'lonsda_form_context';

    /** Longest user agent kept. Real ones are far shorter; this is a bound. */
    private const MAX_USER_AGENT = 255;

    /** @var array{form_id:int,errors:array,values:array,notice:string,success:bool}|null */
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

    /**
     * Discards the outcome of the request's submission.
     *
     * One request handles one submission, so nothing in normal use needs this.
     * The self tests do: they post several times in a single request, and a
     * result left over from the previous one would be read as this one's.
     */
    public static function forget(): void
    {
        self::$result = null;
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
                'success' => false,
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
                'success' => false,
            ];

            return;
        }

        if (FormBuilder::recaptchaActive($form['settings'] ?? []) && !self::recaptchaPassed()) {
            $errors['_recaptcha'] = __('Please confirm you are not a robot.', 'lonsda-light-form');
        }

        /** @var array $errors */
        $errors = (array) apply_filters(self::FILTER_VALIDATE, $errors, $values, $form);

        $context = self::context($form_id);

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
                'success' => false,
            ];

            return;
        }

        Logs::add('submission', 'Accepted.', ['form' => $form_id, 'fields' => count($values)]);

        do_action(self::HOOK_SUBMITTED, $values, $form, $context);

        self::$result = [
            'form_id' => $form_id,
            'errors'  => [],
            'values'  => [],
            // Wording lives on the form and is resolved by the renderer, which
            // is the only place that has the form definition to hand. This is
            // the fallback for anything reading the result directly.
            'notice'  => __('Thank you — your message has been sent.', 'lonsda-light-form'),
            'success' => true,
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
        $token  = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
        $result = self::verifyRecaptcha($token);

        // Unreachable verification is the site's problem, not the visitor's.
        // Failing closed would silently break every form the moment Google is
        // slow, so an outage lets submissions through.
        return $result['success'] || $result['unreachable'];
    }

    /**
     * Asks Google whether a token is good.
     *
     * Public and returning the detail rather than a bool, because the settings
     * page tests the keys through exactly this path — a test that used its own
     * request would be testing itself rather than what forms actually do.
     *
     * @return array{success: bool, unreachable: bool, errors: string[]}
     */
    public static function verifyRecaptcha(string $token): array
    {
        $result = ['success' => false, 'unreachable' => false, 'errors' => []];
        $secret = trim((string) Settings::get('recaptcha_secret_key', ''));

        if ('' === $secret) {
            $result['errors'][] = 'missing-input-secret';

            return $result;
        }

        if ('' === $token) {
            $result['errors'][] = 'missing-input-response';

            return $result;
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

            $result['unreachable'] = true;
            $result['errors'][]    = $response->get_error_message();

            return $result;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        $result['success'] = is_array($body) && !empty($body['success']);
        $result['errors']  = is_array($body) && !empty($body['error-codes'])
            ? array_map('strval', (array) $body['error-codes'])
            : $result['errors'];

        return $result;
    }

    private static function clientIp(): string
    {
        // REMOTE_ADDR only. X-Forwarded-For and friends are set by whoever sent
        // the request unless a proxy is known to overwrite them, so trusting
        // one by default would let a submitter choose the IP recorded against
        // them. A site behind a proxy can supply the real one via the filter on
        // context().
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }

    /**
     * Metadata recorded with every submission, whatever the form.
     *
     * Gathered in one place so that every listener gets the same shape, and so
     * a form does not have to opt into it. Passed to both the accepted and the
     * rejected hook: a rejection is often the more interesting one to look at.
     *
     * @return array{
     *     form_id: int, post_id: int|null, language: string, time: int,
     *     submitted_at: string, ip: string, user_agent: string
     * }
     */
    public static function context(int $form_id): array
    {
        $post_id = (int) get_the_ID();

        $context = [
            'form_id' => $form_id,
            // Null rather than 0 when there is no post: a form can be rendered
            // outside the loop, and 0 would read as a real id.
            'post_id' => $post_id > 0 ? $post_id : null,
            'language' => self::language($post_id),
            'time'     => time(),
            // Alongside the timestamp rather than instead of it: whoever emails
            // this wants something readable, and UTC so it does not shift with
            // the site's timezone setting.
            'submitted_at' => gmdate('Y-m-d H:i:s'),
            'ip'           => self::clientIp(),
            'user_agent'   => self::userAgent(),
        ];

        return (array) apply_filters(self::FILTER_CONTEXT, $context, $form_id);
    }

    /** Truncated: a user agent is self-reported and has no useful upper bound. */
    private static function userAgent(): string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }

        $agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));

        return mb_substr($agent, 0, self::MAX_USER_AGENT);
    }

    /**
     * The language the form was submitted in.
     *
     * Asked of the post rather than the request: the two agree on an ordinary
     * page view, but a form rendered without a post of its own would otherwise
     * report nothing.
     */
    private static function language(int $post_id): string
    {
        if (class_exists('\\Lauzis\\WpPackages\\I18n\\Language')) {
            return \Lauzis\WpPackages\I18n\Language::for_post($post_id);
        }

        // The shared package handles WPML, Polylang and everything else through
        // its filters. Without it there is still a site locale to report.
        return function_exists('determine_locale') ? determine_locale() : '';
    }
}
