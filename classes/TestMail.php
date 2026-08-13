<?php

namespace LonsdaLightForm;

/**
 * Sends a form's notification or auto reply with made-up answers.
 *
 * Goes through the real senders rather than reproducing them. A test that built
 * its own message would prove the test works; this proves the thing that runs
 * when somebody submits the form does, including its placeholders, its
 * translations and whatever a filter does to it on the way out.
 *
 * The answers are invented, so the recipient is overridden and the submission
 * is never stored — nothing here should look afterwards like a real enquiry.
 */
class TestMail
{
    /** What the two buttons can ask for. */
    public const NOTIFICATION = 'notification';
    public const AUTO_REPLY   = 'auto_reply';

    /**
     * Sends one test message.
     *
     * @param int    $post_id Form being edited.
     * @param string $which   self::NOTIFICATION or self::AUTO_REPLY.
     * @param string $to      Address to send to.
     * @param string $locale  Language to send it in. Empty for the site's own.
     * @return array{sent: bool, message: string, subject: string}
     */
    public static function send(int $post_id, string $which, string $to, string $locale = ''): array
    {
        if (!current_user_can('manage_options')) {
            return self::fail(__('You are not allowed to send test emails.', 'lonsda-light-form'));
        }

        $to = sanitize_email($to);

        if ('' === $to || !is_email($to)) {
            return self::fail(__('That is not a valid email address.', 'lonsda-light-form'));
        }

        $form_id = Forms::tableIdForPost($post_id);
        $form    = $form_id ? Forms::get($form_id) : null;

        if (!$form) {
            return self::fail(__('Save the form once before testing it.', 'lonsda-light-form'));
        }

        $values  = self::sampleValues($form, $to);
        $context = self::sampleContext($form_id, $to);
        $locale  = self::acceptLocale($locale);

        if ('' !== $locale) {
            // Recorded on the submission as well as switched to below. The auto
            // reply reads the language off the submission rather than off the
            // request, so a test that only switched the request would be
            // testing something no visitor ever does.
            $context['locale']   = $locale;
            $context['language'] = self::languageFor($locale);
        }

        // The notification, unlike the reply, follows the language of the
        // request. Both are switched here anyway: the question this tab answers
        // is what the message looks like in a given language, and answering it
        // for one of the two would be a trap.
        $switched = '' !== $locale
            ? Locale::switchTo($locale, (string) $context['language'])
            : false;

        $captured = null;

        // The recipient is replaced at the last moment, through the same filter
        // a site would use. Everything before it — templates, placeholders,
        // translation, Reply-To — is exactly what a real submission produces.
        $redirect = static function ($mail) use ($to, &$captured) {
            $mail['to']      = $to;
            $mail['subject'] = self::mark($mail['subject'] ?? '');
            $captured        = $mail;

            return $mail;
        };

        $filter = self::AUTO_REPLY === $which ? AutoReply::FILTER_MAIL : Notifications::FILTER_MAIL;

        add_filter($filter, $redirect, 99);

        try {
            // Called directly rather than by firing the submitted hook: that
            // would also reach the entries listener, and a test would be stored
            // as a real submission — and any listener a theme has added would
            // run too.
            if (self::AUTO_REPLY === $which) {
                AutoReply::send($values, $form, $context);
            } else {
                Notifications::send($values, $form, $context);
            }
        } finally {
            remove_filter($filter, $redirect, 99);

            // Before anything below is worded: what comes back is read by
            // whoever pressed the button, not by the visitor being imagined.
            if ($switched) {
                Locale::restore();
            }
        }

        if (null === $captured) {
            // Logged as well as reported: "nothing happened" is the hardest
            // outcome to chase afterwards, and the reason is known here.
            Logs::add('test-mail', 'Test email produced nothing.', [
                'form'   => $form_id,
                'kind'   => $which,
                'reason' => wp_strip_all_tags(self::whyNothingWasSent($which, $form)),
            ]);

            return self::fail(self::whyNothingWasSent($which, $form));
        }

        $used = '' === $locale ? determine_locale() : $locale;

        // The message itself, not just that one went. A test send is the one
        // place where logging the whole thing is right: the answers in it are
        // invented, an administrator asked for it deliberately, and the reason
        // to press the button at all is usually to find out why what arrives is
        // not what was expected. A real submission is logged without its
        // contents — that is somebody's message, and a log is not the place
        // for it.
        Logs::add('test-mail', 'Test email sent.', [
            'form'    => $form_id,
            'kind'    => $which,
            'to'      => $to,
            'locale'  => $used,
            // Answers the question the subject line usually raises next: an
            // English subject with no file to translate it is a translation
            // nobody has written, and an English subject with a file present
            // is something else entirely.
            'strings' => is_readable(Translations::path($used))
                ? 'translation file present for ' . $used
                : 'no translation file for ' . $used,
            'subject' => (string) ($captured['subject'] ?? ''),
            'message' => (string) ($captured['message'] ?? ''),
            'headers' => (array) ($captured['headers'] ?? []),
        ]);

        return [
            'sent'    => true,
            'subject' => (string) ($captured['subject'] ?? ''),
            'message' => '' === $locale
                ? sprintf(
                    /* translators: %s: email address */
                    __('Sent to %s. If it does not arrive, the plugin log will say whether it left this site.', 'lonsda-light-form'),
                    $to
                )
                : sprintf(
                    /* translators: 1: email address, 2: locale, e.g. lv_LV */
                    __('Sent to %1$s in %2$s. Anything still in English there has no translation yet.', 'lonsda-light-form'),
                    $to,
                    $locale
                ),
        ];
    }

    /**
     * The locale to test in, or '' for the site's own.
     *
     * Checked against what the site actually offers rather than taken as given:
     * this arrives from a browser and decides which language the whole request
     * is switched into. A name that is merely well-formed is not enough.
     *
     * A locale with a translation file installed counts even when the offered
     * list does not mention it — the file being there is the site saying it
     * means something, and it is the case worth testing.
     */
    private static function acceptLocale(string $locale): string
    {
        $locale = Translations::sanitizeLocale($locale);

        if ('' === $locale) {
            return '';
        }

        if (isset(Translations::locales()[$locale]) || isset(Translations::installed()[$locale])) {
            return $locale;
        }

        return '';
    }

    /** The bare language code a locale belongs to: lv_LV is lv. */
    private static function languageFor(string $locale): string
    {
        if (class_exists('\\Lauzis\\WpPackages\\I18n\\Language')) {
            return \Lauzis\WpPackages\I18n\Language::normalize($locale);
        }

        $parts = preg_split('/[_-]/', $locale);

        return is_array($parts) ? strtolower($parts[0]) : '';
    }

    /**
     * Why a send produced nothing, in terms of what to go and change.
     *
     * "Nothing happened" is the least useful thing a test can report.
     */
    private static function whyNothingWasSent(string $which, array $form): string
    {
        $settings = $form['settings'] ?? [];

        if (self::AUTO_REPLY === $which) {
            if (empty($settings['auto_reply'])) {
                return __('The auto reply is switched off for this form — turn it on on the Auto reply tab.', 'lonsda-light-form');
            }

            return __('This form has no field with email validation, so an auto reply has nowhere to go.', 'lonsda-light-form');
        }

        if ('' === trim((string) ($settings['notify_to'] ?? ''))) {
            return __('No recipient is set for this form — add one on the Notifications tab.', 'lonsda-light-form');
        }

        return __('Nothing was sent. A filter may have cancelled it.', 'lonsda-light-form');
    }

    /**
     * Plausible answers for every field the form asks for.
     *
     * Filled in rather than left blank so the message looks like a real one: an
     * empty template tells you nothing about how it reads when full.
     */
    private static function sampleValues(array $form, string $to): array
    {
        $values = [];

        foreach ($form['settings']['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');

            if ('' === $name) {
                continue;
            }

            switch ($field['type'] ?? 'text') {
                case 'checkbox':
                    $values[$name] = true;
                    break;

                case 'textarea':
                    $values[$name] = __("This is a test message.\nIt has two lines, so you can see how they come out.", 'lonsda-light-form');
                    break;

                default:
                    // A field the form validates as an email gets the address
                    // being tested, so Reply-To and the auto reply have
                    // somewhere real to point.
                    $values[$name] = 'email' === ($field['validation'] ?? '')
                        ? $to
                        : sprintf(
                            /* translators: %s: field label */
                            __('Test %s', 'lonsda-light-form'),
                            $field['label'] ?? $name
                        );
            }
        }

        return $values;
    }

    /** Metadata describing the test rather than pretending to be a visitor. */
    private static function sampleContext(int $form_id, string $to): array
    {
        $context = Submission::context($form_id);

        // Not a visitor's address. Recording the administrator's own IP against
        // a made-up submission would be misleading in a message that may well
        // be forwarded.
        $context['ip']         = '';
        $context['user_agent'] = '';

        return $context;
    }

    /** Marks the subject, so a test is never mistaken for a real enquiry. */
    private static function mark(string $subject): string
    {
        return '[' . __('TEST', 'lonsda-light-form') . '] ' . $subject;
    }

    /** @return array{sent: bool, message: string, subject: string} */
    private static function fail(string $message): array
    {
        return ['sent' => false, 'message' => $message, 'subject' => ''];
    }
}
