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
     * @return array{sent: bool, message: string, subject: string}
     */
    public static function send(int $post_id, string $which, string $to): array
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

        // Called directly rather than by firing the submitted hook: that would
        // also reach the entries listener, and a test would be stored as a real
        // submission — and any listener a theme has added would run too.
        if (self::AUTO_REPLY === $which) {
            AutoReply::send($values, $form, $context);
        } else {
            Notifications::send($values, $form, $context);
        }

        remove_filter($filter, $redirect, 99);

        if (null === $captured) {
            return self::fail(self::whyNothingWasSent($which, $form));
        }

        Logs::add('test-mail', 'Test email sent.', [
            'form' => $form_id,
            'kind' => $which,
            'to'   => $to,
        ]);

        return [
            'sent'    => true,
            'subject' => (string) ($captured['subject'] ?? ''),
            'message' => sprintf(
                /* translators: %s: email address */
                __('Sent to %s. If it does not arrive, the plugin log will say whether it left this site.', 'lonsda-light-form'),
                $to
            ),
        ];
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
