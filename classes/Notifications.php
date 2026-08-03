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

        $mail = [
            'to'      => $recipients,
            'subject' => self::subject($settings, $form),
            'message' => self::body($values, $form, $context),
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

    /** @param array $settings Stored form settings. */
    private static function subject(array $settings, array $form): string
    {
        $subject = trim((string) ($settings['notify_subject'] ?? ''));
        $title   = (string) ($form['title'] ?? '');

        if ('' === $subject) {
            /* translators: %s: form title */
            $subject = sprintf(__('New submission: %s', 'lonsda-light-form'), $title);
        }

        return str_replace(
            ['{form_title}', '{site_name}'],
            [$title, (string) get_bloginfo('name')],
            $subject
        );
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
     * The message, as plain text.
     *
     * Plain rather than HTML because it is read, not designed, and a plain
     * message cannot render wrongly or be held back as suspicious markup.
     */
    private static function body(array $values, array $form, array $context): string
    {
        $lines = [
            sprintf(
                /* translators: %s: form title */
                __('A form was submitted: %s', 'lonsda-light-form'),
                (string) ($form['title'] ?? '')
            ),
            '',
        ];

        // Walked in field order rather than in the order values arrived, so the
        // mail reads like the form and a missing answer is visible as a gap.
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

        $page = !empty($context['post_id']) ? get_permalink((int) $context['post_id']) : '';

        $lines[] = '';
        $lines[] = str_repeat('-', 40);
        $lines[] = __('Submitted', 'lonsda-light-form') . ': ' . ($context['submitted_at'] ?? '') . ' UTC';

        if ($page) {
            $lines[] = __('Page', 'lonsda-light-form') . ': ' . $page;
        }

        if (!empty($context['language'])) {
            $lines[] = __('Language', 'lonsda-light-form') . ': ' . $context['language'];
        }

        $lines[] = __('IP address', 'lonsda-light-form') . ': ' . ($context['ip'] ?? '');

        return implode("\n", $lines);
    }
}
