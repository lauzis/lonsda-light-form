<?php

namespace LonsdaLightForm;

/**
 * Emails the person who submitted the form, saying it arrived.
 *
 * A listener on the public hook like the others, so it can be removed without
 * touching the submission handler.
 *
 * Off unless a form switches it on. This mails an address a stranger typed into
 * a public form, which is a thing to do deliberately: it is how a form becomes
 * a way to send mail to someone who never asked for it, and every message it
 * sends is attributable to the site.
 */
class AutoReply
{
    /** Fired before the reply goes out, so it can be altered or stopped. */
    public const FILTER_MAIL = 'lonsda_form_auto_reply';

    public static function init(): void
    {
        // After the notification, so the site owner hears about a submission
        // before the sender is told it arrived.
        add_action(Submission::HOOK_SUBMITTED, [self::class, 'send'], 20, 3);
    }

    /**
     * @param array $values  Field name => submitted value.
     * @param array $form    Stored definition.
     * @param array $context Submission metadata.
     */
    public static function send(array $values, array $form, array $context): void
    {
        $settings = $form['settings'] ?? [];

        if (empty($settings['auto_reply'])) {
            return;
        }

        $to = self::recipient($values, $form);

        if ('' === $to) {
            Logs::add('auto-reply', 'No address was collected, so no auto reply was sent.', [
                'form' => $form['id'] ?? null,
            ]);

            return;
        }

        // The language the form was submitted in, recorded with the submission
        // rather than read back off the request. This is the whole message —
        // wording, defaults, the filter's contribution and the send — so a
        // reply reaches the visitor in the language they wrote in.
        //
        // It is usually the language the request is already in, and then this
        // costs nothing. It stops mattering only until something sends the
        // reply from somewhere else: a queue, a retry, wp-cron, an admin
        // screen resending it. Then the recorded language is the only one that
        // still knows who this is being written to.
        $switched = Locale::switchTo(
            (string) ($context['locale'] ?? ''),
            (string) ($context['language'] ?? '')
        );

        try {
            $mail = self::compose($to, $values, $form, $context);

            if (!$mail || empty($mail['to'])) {
                return;
            }

            // Both parts: the HTML as written and a text version built from it.
            // A client showing plain text otherwise gets the markup flattened
            // into one unbroken paragraph.
            $sent = Mail::html(
                $mail['to'],
                (string) ($mail['subject'] ?? ''),
                (string) ($mail['message'] ?? ''),
                (array) ($mail['headers'] ?? [])
            );
        } finally {
            // In a finally because the filter above is somebody else's code:
            // whatever it throws, the rest of the request carries on in the
            // language it started in.
            if ($switched) {
                Locale::restore();
            }
        }

        if ($sent) {
            Logs::add('auto-reply', 'Auto reply sent.', [
                'form' => $form['id'] ?? null,
                'to'   => $mail['to'],
            ]);

            return;
        }

        // Worth recording even though nobody is waiting on it: a site whose
        // auto replies are failing is a site whose notifications probably are
        // too, and this is the half nobody notices.
        Logs::error('auto-reply', 'Auto reply could not be sent.', [
            'form' => $form['id'] ?? null,
            'to'   => $mail['to'],
        ]);
    }

    /**
     * The message, built in whatever language is current.
     *
     * Separate from send() so that everything language-dependent happens in one
     * place, inside the switch: the wording, the shipped defaults behind it,
     * the Yes and No a checkbox becomes, and the filter, which is where a site
     * adds its own signature and will want that translated too.
     *
     * @param string $to      Verified address.
     * @param array  $values  Field name => submitted value.
     * @param array  $form    Stored definition.
     * @param array  $context Submission metadata.
     * @return array to, subject, message, headers — or empty to send nothing.
     */
    private static function compose(string $to, array $values, array $form, array $context): array
    {
        $settings = $form['settings'] ?? [];
        $tokens   = Notifications::placeholders($values, $form, $context);

        $subject = trim((string) ($settings['auto_reply_subject'] ?? ''));
        $subject = '' === $subject ? FormBuilder::defaultAutoReplySubject() : $subject;

        $message = trim((string) ($settings['auto_reply_message'] ?? ''));
        $message = '' === $message ? FormBuilder::defaultAutoReplyMessage() : $message;

        // Translated before the placeholders are filled in, so a translation
        // can move {name} to wherever that language wants it.
        $subject = Strings::get($subject, (string) ($settings['auto_reply_subject_key'] ?? ''));
        $message = Strings::get($message, (string) ($settings['auto_reply_message_key'] ?? ''));

        $mail = [
            'to'      => $to,
            'subject' => self::replace($subject, $tokens),
            // Authored in a visual editor, so it goes out as HTML — and through
            // wp_kses_post first, because it is written into an email either
            // way and nothing here needs script or an iframe.
            'message' => wp_kses_post(self::replace($message, $tokens)),
            'headers' => ['Content-Type: text/html; charset=UTF-8'],
        ];

        /**
         * Filters the auto reply before it is sent.
         *
         * Runs with the locale switched to the language of the submission, so
         * anything this adds can be translated the same way the rest is.
         *
         * Return an empty array to send nothing.
         *
         * @param array $mail    to, subject, message, headers.
         * @param array $values  Submitted values.
         * @param array $form    Stored definition.
         * @param array $context Submission metadata.
         */
        return (array) apply_filters(self::FILTER_MAIL, $mail, $values, $form, $context);
    }

    /**
     * The address to reply to.
     *
     * Only a field the form validates as an email. Guessing at a field merely
     * named something like one would mean mailing whatever a stranger typed
     * into a box that was never checked.
     */
    private static function recipient(array $values, array $form): string
    {
        foreach ($form['settings']['fields'] ?? [] as $field) {
            if ('email' !== ($field['validation'] ?? '')) {
                continue;
            }

            $email = sanitize_email((string) ($values[$field['name'] ?? ''] ?? ''));

            if ('' !== $email && is_email($email)) {
                return $email;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $tokens
     */
    private static function replace(string $text, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $text);
    }
}
