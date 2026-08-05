<?php

namespace LonsdaLightForm;

/**
 * Emails a submission to whoever should hear about it.
 *
 * Built as a listener on the public hook rather than wired into the submission
 * handler, so it has no more access than a theme doing the same job — and so
 * removing the action switches it off entirely. The plugin still hands every
 * submission on; this is one listener that happens to ship with it.
 *
 * Nothing is sent unless a form names a recipient. A default of "the admin
 * address" would start mailing whoever installed the plugin the moment a form
 * went live, which is a decision that should be made rather than inherited.
 */
class Notifications
{
    /** Fired before the notification goes out, so it can be altered or stopped. */
    public const FILTER_MAIL = 'lonsda_form_notification';

    public static function init(): void
    {
        add_action(Submission::HOOK_SUBMITTED, [self::class, 'send'], 10, 3);
    }

    /**
     * @param array $values  Field name => submitted value.
     * @param array $form    Stored definition.
     * @param array $context Submission metadata.
     */
    public static function send(array $values, array $form, array $context): void
    {
        $settings   = $form['settings'] ?? [];
        $recipients = self::recipients((string) ($settings['notify_to'] ?? ''));

        if (!$recipients) {
            return;
        }

        $tokens = self::placeholders($values, $form, $context);

        $mail = [
            'to'      => $recipients,
            'subject' => self::subject($settings, $form, $tokens),
            'message' => self::message($settings, $tokens),
            'headers' => self::headers($values, $settings),
        ];

        /**
         * Filters the notification before it is sent.
         *
         * Return an empty array to send nothing.
         *
         * @param array $mail    to, subject, message, headers.
         * @param array $values  Submitted values.
         * @param array $form    Stored definition.
         * @param array $context Submission metadata.
         */
        $mail = (array) apply_filters(self::FILTER_MAIL, $mail, $values, $form, $context);

        if (!$mail || empty($mail['to'])) {
            return;
        }

        $sent = wp_mail(
            $mail['to'],
            (string) ($mail['subject'] ?? ''),
            (string) ($mail['message'] ?? ''),
            (array) ($mail['headers'] ?? [])
        );

        // Recorded either way. A notification that silently fails to send looks
        // exactly like a form nobody filled in, which is the worst way to find
        // out that mail is broken.
        if ($sent) {
            Logs::add('notification', 'Notification sent.', [
                'form' => $form['id'] ?? null,
                'to'   => $mail['to'],
            ]);

            return;
        }

        Logs::error('notification', 'Notification could not be sent.', [
            'form' => $form['id'] ?? null,
            'to'   => $mail['to'],
        ]);
    }

    /**
     * Valid addresses from a free-text list.
     *
     * Separated by comma, semicolon or newline, because people type all three.
     * Anything that is not an address is dropped rather than passed to wp_mail,
     * where one bad entry can lose the whole send.
     *
     * @return string[]
     */
    public static function recipients(string $raw): array
    {
        $parts = preg_split('/[,;\r\n]+/', $raw) ?: [];
        $valid = [];

        foreach ($parts as $part) {
            $email = sanitize_email(trim($part));

            if ('' !== $email && is_email($email) && !in_array($email, $valid, true)) {
                $valid[] = $email;
            }
        }

        return $valid;
    }

    /**
     * Everything {in_braces} stands for, for both the subject and the message.
     *
     * Field answers first, then the built-ins, so a field named site_name
     * cannot quietly displace the site's own — the fixed set has to mean the
     * same thing on every form.
     *
     * @return array<string, string>
     */
    public static function placeholders(array $values, array $form, array $context): array
    {
        $tokens = [];

        foreach ($form['settings']['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');

            if ('' === $name) {
                continue;
            }

            $value = $values[$name] ?? null;

            if ('checkbox' === ($field['type'] ?? '')) {
                $value = $value ? __('Yes', 'lonsda-light-form') : __('No', 'lonsda-light-form');
            }

            $tokens['{' . $name . '}'] = trim((string) $value);
        }

        $page = !empty($context['post_id']) ? (string) get_permalink((int) $context['post_id']) : '';

        $tokens['{all_fields}']   = self::fieldList($values, $form);
        $tokens['{form_title}']   = (string) ($form['title'] ?? '');
        $tokens['{site_name}']    = (string) get_bloginfo('name');
        $tokens['{site_url}']     = home_url();
        $tokens['{submitted_at}'] = (string) ($context['submitted_at'] ?? '');
        $tokens['{page_title}']   = !empty($context['post_id']) ? (string) get_the_title((int) $context['post_id']) : '';
        $tokens['{page_url}']     = $page;
        $tokens['{language}']     = (string) ($context['language'] ?? '');
        $tokens['{locale}']       = (string) ($context['locale'] ?? '');
        $tokens['{ip}']           = (string) ($context['ip'] ?? '');
        $tokens['{user_agent}']   = (string) ($context['user_agent'] ?? '');

        return $tokens;
    }

    /**
     * @param array $settings Stored form settings.
     * @param array $tokens   Placeholder => replacement.
     */
    private static function subject(array $settings, array $form, array $tokens): string
    {
        $subject = trim((string) ($settings['notify_subject'] ?? ''));

        if ('' === $subject) {
            /* translators: %s: form title */
            $subject = sprintf(__('New submission: %s', 'lonsda-light-form'), (string) ($form['title'] ?? ''));
        }

        // Translated before the placeholders are filled in, so a translation
        // can put {form_title} wherever that language wants it.
        $subject = Strings::get($subject, (string) ($settings['notify_subject_key'] ?? ''));

        return self::replace($subject, $tokens);
    }

    /**
     * The message: the form's own template, or every field when it has none.
     *
     * @param array $settings Stored form settings.
     * @param array $tokens   Placeholder => replacement.
     */
    private static function message(array $settings, array $tokens): string
    {
        $template = trim((string) ($settings['notify_message'] ?? ''));

        if ('' === $template) {
            return self::defaultBody($tokens);
        }

        return self::replace(
            Strings::get($template, (string) ($settings['notify_message_key'] ?? '')),
            $tokens
        );
    }

    /**
     * @param array<string, string> $tokens
     */
    private static function replace(string $text, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $text);
    }

    /**
     * Reply-To set to the submitter where the form collected an address.
     *
     * The point of a notification is usually to reply to it, and hunting the
     * address out of the body to paste into a new mail is the step this saves.
     *
     * @return string[]
     */
    private static function headers(array $values, array $settings): array
    {
        $field = trim((string) ($settings['notify_reply_to'] ?? ''));

        if ('' === $field || !isset($values[$field])) {
            return [];
        }

        $email = sanitize_email((string) $values[$field]);

        return ('' !== $email && is_email($email)) ? ['Reply-To: ' . $email] : [];
    }

    /**
     * The message used when a form has no template of its own.
     *
     * Plain rather than HTML because it is read, not designed, and a plain
     * message cannot render wrongly or be held back as suspicious markup.
     *
     * @param array<string, string> $tokens
     */
    private static function defaultBody(array $tokens): string
    {
        $lines = [
            sprintf(
                /* translators: %s: form title */
                __('A form was submitted: %s', 'lonsda-light-form'),
                $tokens['{form_title}'] ?? ''
            ),
            '',
            $tokens['{all_fields}'] ?? '',
            '',
            str_repeat('-', 40),
            __('Submitted', 'lonsda-light-form') . ': ' . ($tokens['{submitted_at}'] ?? '') . ' UTC',
        ];

        if ('' !== ($tokens['{page_url}'] ?? '')) {
            $lines[] = __('Page', 'lonsda-light-form') . ': ' . $tokens['{page_url}'];
        }

        if ('' !== ($tokens['{language}'] ?? '')) {
            $lines[] = __('Language', 'lonsda-light-form') . ': ' . $tokens['{language}'];
        }

        $lines[] = __('IP address', 'lonsda-light-form') . ': ' . ($tokens['{ip}'] ?? '');

        return implode("\n", $lines);
    }

    /**
     * Every field and its answer, one per line.
     *
     * Walked in field order rather than in the order values arrived, so it
     * reads like the form and a missing answer is visible as a gap.
     */
    private static function fieldList(array $values, array $form): string
    {
        $lines = [];

        foreach ($form['settings']['fields'] ?? [] as $field) {
            $name  = (string) ($field['name'] ?? '');
            $value = $values[$name] ?? null;

            if ('checkbox' === ($field['type'] ?? '')) {
                $value = $value ? __('Yes', 'lonsda-light-form') : __('No', 'lonsda-light-form');
            }

            $value = trim((string) $value);

            $lines[] = (string) ($field['label'] ?? $name) . ': '
                . ('' === $value ? __('(not answered)', 'lonsda-light-form') : $value);
        }

        return implode("\n", $lines);
    }
}
