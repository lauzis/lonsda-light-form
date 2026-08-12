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

        // Both parts, for the same reason as the auto reply: a client showing
        // plain text should not get the markup run together into one line.
        $sent = Mail::html(
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
     * The fixed placeholders, described, for anywhere they have to be listed.
     *
     * One list, because there is more than one screen that has to show it —
     * the form editor and the translations page — and two copies of it is how
     * a screen ends up naming a token that does not exist. The names here are
     * the names placeholders() produces, underscores and all: {site_name} is
     * not {site-name}, and the only defence against that is a list drawn from
     * the same place the substitution is.
     *
     * @return array<string, string> Token => what it becomes.
     */
    public static function placeholderReference(): array
    {
        return [
            '{all_fields}'         => __('Every field and its answer, label in bold', 'lonsda-light-form'),
            '{submission_details}' => __('Page, language, IP and time, omitting any it lacks', 'lonsda-light-form'),
            '{form_title}'         => __('This form\'s title', 'lonsda-light-form'),
            '{site_name}'          => __('The site title', 'lonsda-light-form'),
            '{site_url}'           => __('The site address', 'lonsda-light-form'),
            '{submitted_at}'       => __('When it was submitted, UTC', 'lonsda-light-form'),
            '{page_title}'         => __('The page the form was on', 'lonsda-light-form'),
            '{page_url}'           => __('That page\'s address', 'lonsda-light-form'),
            '{language}'           => __('Language code, e.g. lv', 'lonsda-light-form'),
            '{locale}'             => __('Full locale, e.g. lv_LV', 'lonsda-light-form'),
            '{ip}'                 => __('Submitter\'s IP address', 'lonsda-light-form'),
            '{user_agent}'         => __('Their browser', 'lonsda-light-form'),
        ];
    }

    /**
     * Everything {in_braces} stands for, for both the subject and the message.
     *
     * Field answers first, then the built-ins, so a field named site_name
     * cannot quietly displace the site's own — the fixed set has to mean the
     * same thing on every form.
     *
     * Built in whatever language is current, so the words this produces — a
     * ticked box, a field's label — match the wording they are substituted into.
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
                $value = $value ? Strings::general('word_yes') : Strings::general('word_no');
            }

            $tokens['{' . $name . '}'] = trim((string) $value);
        }

        $page = !empty($context['post_id']) ? (string) get_permalink((int) $context['post_id']) : '';

        $tokens['{all_fields}']         = self::fieldList($values, $form);
        $tokens['{submission_details}'] = self::details($context);
        // Translated, not taken raw: this one is written into a message that
        // may well be going out in a language the title was never in.
        $tokens['{form_title}']   = Strings::get(
            (string) ($form['title'] ?? ''),
            (string) ($form['settings']['title_key'] ?? '')
        );
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
            $template = FormBuilder::defaultNotificationMessage();
        }

        $template = Strings::get($template, (string) ($settings['notify_message_key'] ?? ''));
        $body     = self::replace($template, $tokens);

        // Paragraphed when the wording arrived without any of its own, which is
        // a wider rule than the one this used to apply: a hand-written body is
        // plain text and wants it, and so does a translation, which is typed
        // into a box that holds no markup either.
        //
        // Judged on the template and applied to the result: {all_fields} brings
        // blocks of its own, so judging the result would make every message
        // look marked up, and paragraphing the template would wrap those blocks
        // in a paragraph of ours.
        return Strings::hasBlocks($template) ? $body : wpautop($body);
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
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $field   = trim((string) ($settings['notify_reply_to'] ?? ''));

        if ('' === $field || !isset($values[$field])) {
            return $headers;
        }

        $email = sanitize_email((string) $values[$field]);

        if ('' !== $email && is_email($email)) {
            $headers[] = 'Reply-To: ' . $email;
        }

        return $headers;
    }

    /**
     * Where and when it came from, as a small block.
     *
     * A placeholder rather than something the default body assembles in code,
     * because that is what lets the default body be a single string — and a
     * single string is what can be translated like every other message a form
     * sends. Rows with nothing in them are left out, which a flat template
     * could not do for itself.
     */
    private static function details(array $context): string
    {
        $page = !empty($context['post_id']) ? (string) get_permalink((int) $context['post_id']) : '';

        $rows = [
            __('Submitted', 'lonsda-light-form')  => (string) ($context['submitted_at'] ?? ''),
            __('Page', 'lonsda-light-form')       => $page,
            __('Language', 'lonsda-light-form')   => (string) ($context['locale'] ?? ($context['language'] ?? '')),
            __('IP address', 'lonsda-light-form') => (string) ($context['ip'] ?? ''),
        ];

        $lines = [];

        foreach ($rows as $label => $value) {
            if ('' === trim($value)) {
                continue;
            }

            $lines[] = esc_html($label) . ': ' . esc_html($value);
        }

        return $lines ? '<p><small>' . implode('<br>', $lines) . '</small></p>' : '';
    }

    /**
     * Every field and its answer, as blocks: label in bold, answer beneath.
     *
     * Walked in field order rather than in the order values arrived, so it
     * reads like the form and a missing answer is visible as a gap.
     *
     * Everything a visitor typed is escaped. This is the one place where a
     * stranger's words are placed into markup that lands in someone's inbox,
     * and an answer is text however it was typed.
     */
    private static function fieldList(array $values, array $form): string
    {
        $blocks = [];

        foreach ($form['settings']['fields'] ?? [] as $field) {
            $name  = (string) ($field['name'] ?? '');
            $value = $values[$name] ?? null;

            if ('checkbox' === ($field['type'] ?? '')) {
                $value = $value ? Strings::general('word_yes') : Strings::general('word_no');
            }

            $value = trim((string) $value);

            $answer = '' === $value
                ? '<em>' . esc_html(Strings::general('word_not_answered')) . '</em>'
                // Line breaks kept: a textarea answer is written in paragraphs
                // and collapsing it into one line loses what the person meant.
                : nl2br(esc_html($value));

            // Translated like the label on the form is, and for the same
            // reason: this list is read by whoever the mail is addressed to. An
            // auto reply otherwise arrived in the visitor's language with every
            // question still in English.
            $label = Strings::get(
                (string) ($field['label'] ?? $name),
                (string) ($field['translation_key'] ?? '')
            );

            $blocks[] = '<p><strong>' . esc_html($label) . ':</strong><br>'
                . $answer . '</p>';
        }

        return implode("\n", $blocks);
    }
}
