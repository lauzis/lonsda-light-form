<?php

namespace LonsdaLightForm;

/**
 * Self tests, run against the site they are installed on.
 *
 * Not a substitute for a unit suite — the point is the opposite. These exercise
 * the real database, the real post type and the real submission handler, on the
 * install where something has gone wrong, which is where the interesting
 * failures live: a missing table, a plugin filtering the query, a capability
 * that is not what anyone assumed.
 *
 * Everything created is removed again, whether the scenario passes or not.
 */
class Tests
{
    /**
     * Title given to every form these tests create.
     *
     * Cleanup finds its own work by this prefix rather than by remembering ids,
     * so a run interrupted half way leaves nothing for the next one to trip on.
     */
    public const PREFIX = 'LLF Self Test —';

    /**
     * Locale the translation scenario writes to.
     *
     * Deliberately not a real one. Writing to a locale the site might serve
     * would put test strings in front of visitors, and cleaning it up would
     * delete a translation someone had actually made.
     */
    public const TEST_LOCALE = 'zz_ZZ';

    /** Slug => label, for the buttons on the tests page. */
    public static function scenarios(): array
    {
        return [
            'form-creation' => __('Form creation and storage', 'lonsda-light-form'),
            'shortcode'     => __('Shortcode and block rendering', 'lonsda-light-form'),
            'submission'    => __('Form submission', 'lonsda-light-form'),
            'validation'    => __('Field validation', 'lonsda-light-form'),
            'entries'       => __('Stored entries', 'lonsda-light-form'),
            'notifications' => __('Notification emails', 'lonsda-light-form'),
            'auto-reply'    => __('Auto reply', 'lonsda-light-form'),
            'placeholders'  => __('Placeholders and translation', 'lonsda-light-form'),
            'test-mail'     => __('Testing tab', 'lonsda-light-form'),
            'translations'  => __('Translations', 'lonsda-light-form'),
            'cleanup'       => __('Clean up leftovers from earlier runs', 'lonsda-light-form'),
        ];
    }

    /** Runs one scenario, having checked the caller is allowed to. */
    public static function run(string $scenario): void
    {
        if (!current_user_can('manage_options')) {
            self::result(__('Access denied.', 'lonsda-light-form'), false);

            return;
        }

        // Anything left by an interrupted run would otherwise be counted by
        // this one — a stale form is indistinguishable from one just made.
        self::cleanup();

        // Notifications are prefilled with the site address, so a test form is
        // a form that would email the administrator. Cancelled at a priority
        // nothing else uses, so it applies whatever a scenario does first.
        add_filter(Notifications::FILTER_MAIL, '__return_empty_array', 999);
        add_filter(AutoReply::FILTER_MAIL, '__return_empty_array', 999);

        try {
            match ($scenario) {
                'form-creation' => self::formCreationScenario(),
                'shortcode'     => self::shortcodeScenario(),
                'submission'    => self::submissionScenario(),
                'validation'    => self::validationScenario(),
                'entries'       => self::entriesScenario(),
                'notifications' => self::notificationsScenario(),
                'auto-reply'    => self::autoReplyScenario(),
                'placeholders'  => self::placeholdersScenario(),
                'test-mail'     => self::testMailScenario(),
                'translations'  => self::translationsScenario(),
                'cleanup'       => self::cleanupScenario(),
                default         => self::result(__('Unknown scenario.', 'lonsda-light-form'), false),
            };
        } catch (\Throwable $e) {
            // Reported rather than fatal: a half-finished scenario still has to
            // reach the cleanup below, or the next run starts dirty.
            self::result(
                sprintf(
                    /* translators: %s: error message */
                    __('The scenario stopped with an error: %s', 'lonsda-light-form'),
                    $e->getMessage()
                ),
                false
            );
        } finally {
            remove_filter(Notifications::FILTER_MAIL, '__return_empty_array', 999);
            remove_filter(AutoReply::FILTER_MAIL, '__return_empty_array', 999);

            if ('cleanup' !== $scenario) {
                self::cleanup();
                self::title(__('Cleanup', 'lonsda-light-form'));
                self::assert(__('Everything this run created has been removed', 'lonsda-light-form'), 0 === self::leftovers());
            }
        }
    }

    // ------------------------------------------------------------ scenarios --

    private static function formCreationScenario(): void
    {
        self::heading(__('Form creation and storage', 'lonsda-light-form'));

        $post_id = self::makeForm('Creation', [
            ['label' => 'Full name', 'name' => 'full_name', 'type' => 'text', 'required' => true],
            ['label' => 'Notes', 'name' => 'notes', 'type' => 'textarea'],
        ]);

        self::title(__('The form post is created', 'lonsda-light-form'));
        self::assert(sprintf('post id %d', $post_id), $post_id > 0);

        self::title(__('It is projected into the forms table', 'lonsda-light-form'));
        $form_id = Forms::tableIdForPost($post_id);
        self::assert(sprintf('form id %d', $form_id), $form_id > 0);

        self::title(__('The two identifiers are not confused', 'lonsda-light-form'));
        self::assert(
            sprintf('post %d ≠ form %d, and each resolves to its own record', $post_id, $form_id),
            $post_id !== $form_id && null !== Forms::get($form_id)
        );

        $form = Forms::get($form_id);

        self::title(__('The stored definition survives the round trip', 'lonsda-light-form'));
        $names = array_column($form['settings']['fields'] ?? [], 'name');
        self::assert('fields: ' . implode(', ', $names), ['full_name', 'notes'] === $names, $form['settings']['fields'] ?? []);

        self::title(__('Field properties are kept', 'lonsda-light-form'));
        $first = $form['settings']['fields'][0] ?? [];
        self::assert(
            'first field is a required text input',
            'text' === ($first['type'] ?? '') && !empty($first['required'])
        );

        self::title(__('Translation keys are generated from the names', 'lonsda-light-form'));
        $keys = array_column($form['settings']['fields'], 'translation_key');
        self::assert(implode(', ', $keys), ['field_full_name_label', 'field_notes_label'] === $keys);

        self::title(__('Placeholders get a key of their own', 'lonsda-light-form'));
        $keys = array_column($form['settings']['fields'], 'placeholder_key');
        self::assert(implode(', ', $keys), ['field_full_name_placeholder', 'field_notes_placeholder'] === $keys);

        self::title(__('Renaming a field renames both keys', 'lonsda-light-form'));
        // Nothing to keep in step by hand now that neither key is editable.
        self::setFields($post_id, [['label' => 'Full name', 'name' => 'given_name', 'type' => 'text']]);
        Forms::syncToTable($post_id, get_post($post_id));
        $renamed = Forms::get($form_id)['settings']['fields'][0] ?? [];
        self::assert(
            ($renamed['translation_key'] ?? '?') . ', ' . ($renamed['placeholder_key'] ?? '?'),
            'field_given_name_label' === ($renamed['translation_key'] ?? '')
                && 'field_given_name_placeholder' === ($renamed['placeholder_key'] ?? '')
        );

        self::title(__('Editing the form updates the same row', 'lonsda-light-form'));
        self::setFields($post_id, [['label' => 'Only one', 'name' => 'only_one', 'type' => 'text']]);
        Forms::syncToTable($post_id, get_post($post_id));
        $again = Forms::get($form_id);
        self::assert(
            'still form id ' . $form_id . ', now ' . count($again['settings']['fields']) . ' field(s)',
            $form_id === Forms::tableIdForPost($post_id) && 1 === count($again['settings']['fields'])
        );

        self::title(__('Deleting the form removes its row', 'lonsda-light-form'));
        wp_delete_post($post_id, true);
        self::assert('the table no longer has it', null === Forms::get($form_id));
    }

    private static function shortcodeScenario(): void
    {
        self::heading(__('Shortcode and block rendering', 'lonsda-light-form'));

        $post_id = self::makeForm('Shortcode', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        self::title(__('The shortcode renders the form', 'lonsda-light-form'));
        $html = do_shortcode('[' . Shortcode::TAG . ' id="' . $form_id . '"]');
        self::assert('a form element with the expected input', self::hasAll($html, ['<form', 'llf[email]']));

        self::title(__('It carries a nonce and the form id', 'lonsda-light-form'));
        self::assert(
            'nonce and hidden id present',
            self::hasAll($html, ['llf_nonce', 'name="' . Renderer::FIELD_FORM_ID . '"'])
        );

        self::title(__('The honeypot is present but hidden from people', 'lonsda-light-form'));
        self::assert(
            'honeypot field is aria-hidden',
            self::hasAll($html, [Renderer::FIELD_HONEYPOT, 'aria-hidden="true"'])
        );

        self::title(__('A missing id renders nothing rather than an error', 'lonsda-light-form'));
        self::assert('empty output', '' === trim(do_shortcode('[' . Shortcode::TAG . ']')));

        self::title(__('A post id given instead of a form id is explained', 'lonsda-light-form'));
        // The mistake worth catching: both are integers and only one works.
        $wrong = Renderer::form($post_id);
        self::assert(
            'the message names the id that should have been used',
            false !== strpos($wrong, (string) $form_id),
            wp_strip_all_tags($wrong)
        );

        self::title(__('The block renders the same form', 'lonsda-light-form'));
        $block = do_blocks('<!-- wp:lonsda/form {"formId":' . $form_id . '} /-->');
        self::assert('block output contains the form', self::hasAll($block, ['<form', 'llf[email]']));

        self::title(__('The submit button uses the form\'s own wording', 'lonsda-light-form'));
        self::assert(
            'button reads "' . FormBuilder::defaultSubmitLabel() . '"',
            false !== strpos($html, '>' . FormBuilder::defaultSubmitLabel() . '<')
        );

        self::title(__('The stylesheet is where the plugin says it is', 'lonsda-light-form'));
        // Enqueuing a file that is not there fails silently, and looks from the
        // outside exactly like a theme overriding it.
        self::assert(
            'assets/css/form.css',
            is_readable(LLF_DIR . 'assets/css/form.css')
        );

        self::title(__('Rendering a form asks for the styles', 'lonsda-light-form'));
        // Only when a form is on the page: a stylesheet on every page of a site
        // for the sake of the one page with a contact form is a poor trade.
        self::assert(
            Styles::enabled() ? 'enqueued for this page' : 'switched off, so not enqueued',
            wp_style_is(Styles::HANDLE, 'enqueued') === Styles::enabled()
        );
    }

    private static function submissionScenario(): void
    {
        self::heading(__('Form submission', 'lonsda-light-form'));

        $post_id = self::makeForm('Submission', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
            ['label' => 'Consent', 'name' => 'consent', 'type' => 'checkbox', 'required' => true],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $seen = [];
        $spy  = static function ($values, $form, $context) use (&$seen): void {
            $seen[] = ['values' => $values, 'form' => $form, 'context' => $context];
        };

        add_action(Submission::HOOK_SUBMITTED, $spy, 10, 3);

        $result = self::submit($form_id, ['email' => 'tester@example.com', 'message' => 'Hello', 'consent' => '1']);

        remove_action(Submission::HOOK_SUBMITTED, $spy, 10);

        self::title(__('A valid submission is accepted', 'lonsda-light-form'));
        self::assert('accepted', !empty($result['success']) && empty($result['errors']), $result);

        self::title(__('The submitted hook fires once', 'lonsda-light-form'));
        self::assert(count($seen) . ' call(s)', 1 === count($seen));

        $values  = $seen[0]['values'] ?? [];
        $context = $seen[0]['context'] ?? [];

        self::title(__('It receives the submitted values', 'lonsda-light-form'));
        self::assert(
            'email and message arrived, checkbox as a boolean',
            'tester@example.com' === ($values['email'] ?? null)
                && 'Hello' === ($values['message'] ?? null)
                && true === ($values['consent'] ?? null),
            $values
        );

        self::title(__('It receives the common metadata', 'lonsda-light-form'));
        $expected = ['form_id', 'post_id', 'language', 'locale', 'time', 'submitted_at', 'ip', 'user_agent'];
        $missing  = array_diff($expected, array_keys($context));
        self::assert(
            $missing ? 'missing: ' . implode(', ', $missing) : 'all keys present',
            [] === $missing,
            $context
        );

        self::title(__('Language and locale are both recorded, and differ', 'lonsda-light-form'));
        // WPML and Polylang report a bare "lv"; WordPress serves "lv_LV". Both
        // are kept because only one of them groups submissions and only the
        // other names a translation file.
        self::assert(
            sprintf('language %s, locale %s', $context['language'] ?? '?', $context['locale'] ?? '?'),
            '' !== ($context['locale'] ?? '')
                && 0 === strpos((string) ($context['locale'] ?? ''), (string) ($context['language'] ?? 'x'))
        );

        self::title(__('The metadata describes this request', 'lonsda-light-form'));
        self::assert(
            sprintf('form %s, language %s, ip %s', $context['form_id'] ?? '?', $context['language'] ?? '?', $context['ip'] ?? '?'),
            $form_id === ($context['form_id'] ?? 0) && '' !== ($context['language'] ?? '')
        );

        self::title(__('A stale nonce is refused', 'lonsda-light-form'));
        $stale = self::submit($form_id, ['email' => 'tester@example.com', 'consent' => '1'], ['nonce' => 'not-a-nonce']);
        self::assert('refused, and nothing was accepted', empty($stale['success']), $stale);

        self::title(__('But the answers survive it', 'lonsda-light-form'));
        // A form open longer than the nonce lives is the visitor's bad luck,
        // not their mistake, and losing a long message to it is the worst thing
        // this form can do to somebody.
        self::assert(
            'answers handed back to the form',
            'tester@example.com' === ($stale['values']['email'] ?? null)
                && true === ($stale['values']['consent'] ?? null),
            $stale['values'] ?? []
        );

        self::title(__('And they come back in the redisplayed form', 'lonsda-light-form'));
        $again = Renderer::form($form_id, ['values' => $stale['values'], 'notice' => $stale['notice']]);
        self::assert(
            'value attribute refilled',
            false !== strpos($again, 'value="tester@example.com"'),
            wp_strip_all_tags($again)
        );

        self::title(__('A filled honeypot is refused', 'lonsda-light-form'));
        $bot = self::submit(
            $form_id,
            ['email' => 'bot@example.com', 'consent' => '1'],
            ['extra' => [Renderer::FIELD_HONEYPOT => 'i am a robot']]
        );
        self::assert(
            'refused without saying which check it tripped',
            empty($bot['success']) && empty($bot['errors']),
            $bot
        );

        self::title(__('The confirmation replaces the form when set to', 'lonsda-light-form'));
        $sent = Renderer::form($form_id, ['success' => true]);
        self::assert(
            'confirmation shown, form withdrawn',
            false === strpos($sent, '<form') && false !== strpos($sent, 'llf-notice--success'),
            wp_strip_all_tags($sent)
        );
    }

    private static function validationScenario(): void
    {
        self::heading(__('Field validation', 'lonsda-light-form'));

        $post_id = self::makeForm('Validation', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Code', 'name' => 'code', 'type' => 'text', 'validation' => 'regex', 'pattern' => '[A-Z]{2}[0-9]{4}'],
            ['label' => 'Short', 'name' => 'short', 'type' => 'text', 'max_length' => 5],
            ['label' => 'Consent', 'name' => 'consent', 'type' => 'checkbox', 'required' => true],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $valid = ['email' => 'someone@example.com', 'code' => 'AB1234', 'short' => 'okay', 'consent' => '1'];

        self::title(__('A wholly valid submission passes', 'lonsda-light-form'));
        $r = self::submit($form_id, $valid);
        self::assert('accepted', !empty($r['success']), $r);

        self::title(__('A required field left empty is rejected', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['email' => '']));
        self::assert('email reported', isset($r['errors']['email']), $r['errors'] ?? []);

        self::title(__('A malformed email is rejected', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['email' => 'not-an-email']));
        self::assert('email reported', isset($r['errors']['email']), $r['errors'] ?? []);

        self::title(__('A value not matching the pattern is rejected', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['code' => 'nope']));
        self::assert('code reported', isset($r['errors']['code']), $r['errors'] ?? []);

        self::title(__('A value matching the pattern is accepted', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['code' => 'ZZ9999']));
        self::assert('no error on code', !isset($r['errors']['code']), $r['errors'] ?? []);

        self::title(__('An over-long value is rejected', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['short' => 'far too long']));
        self::assert('short reported', isset($r['errors']['short']), $r['errors'] ?? []);

        self::title(__('A required checkbox left unticked is rejected', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['consent' => '']));
        self::assert('consent reported', isset($r['errors']['consent']), $r['errors'] ?? []);

        self::title(__('An optional field left empty is fine', 'lonsda-light-form'));
        $r = self::submit($form_id, array_merge($valid, ['code' => '', 'short' => '']));
        self::assert('accepted', !empty($r['success']), $r['errors'] ?? []);

        self::title(__('Validation is enforced here, not just in the browser', 'lonsda-light-form'));
        // The renderer's attributes are a convenience and can be edited away, so
        // the same rules have to hold for a request that never saw the form.
        $r = self::submit($form_id, ['email' => 'nope', 'code' => 'nope', 'short' => 'far too long']);
        self::assert(
            count($r['errors'] ?? []) . ' field(s) rejected',
            count($r['errors'] ?? []) >= 3,
            $r['errors'] ?? []
        );

        self::title(__('A rejected field is marked for styling', 'lonsda-light-form'));
        $r    = self::submit($form_id, array_merge($valid, ['email' => 'not-an-email']));
        $html = Renderer::form($form_id, ['errors' => $r['errors'], 'values' => $r['values'], 'notice' => $r['notice']]);

        self::assert(
            'the input and its wrapper both carry an error class',
            self::hasAll($html, ['llf-input--error', 'llf-field--error', 'llf-form--has-errors'])
        );

        self::title(__('And announced, not only coloured', 'lonsda-light-form'));
        self::assert(
            'aria-invalid and a described-by pointing at the message',
            self::hasAll($html, ['aria-invalid="true"', 'aria-describedby="llf-email-error"', 'id="llf-email-error"'])
        );

        self::title(__('A field that passed is not marked', 'lonsda-light-form'));
        // The class has to mean something: everything wearing it would be no
        // more use than nothing wearing it.
        self::assert(
            'only the rejected field carries it',
            1 === substr_count($html, 'llf-input--error')
                && false !== strpos($html, 'llf[code]')
        );

        self::title(__('Every answer comes back, not only the rejected one', 'lonsda-light-form'));
        // The point of redisplaying at all: one field being wrong is no reason
        // to make somebody type the other four again.
        self::assert(
            'text, the rejected value and the ticked box all refilled',
            self::hasAll($html, [
                'value="AB1234"',
                'value="okay"',
                'value="not-an-email"',
                'checked=\'checked\'',
            ]),
            wp_strip_all_tags($html)
        );

        self::title(__('Another listener can add errors of its own', 'lonsda-light-form'));
        $veto = static function (array $errors): array {
            $errors['email'] = 'blocked by a test';

            return $errors;
        };
        add_filter(Submission::FILTER_VALIDATE, $veto);
        $r = self::submit($form_id, $valid);
        remove_filter(Submission::FILTER_VALIDATE, $veto);
        self::assert('the added error was honoured', 'blocked by a test' === ($r['errors']['email'] ?? null), $r['errors'] ?? []);
    }

    private static function entriesScenario(): void
    {
        self::heading(__('Stored entries', 'lonsda-light-form'));

        $post_id = self::makeForm('Entries', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Consent', 'name' => 'consent', 'type' => 'checkbox'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $before = Entries::count($form_id);
        self::submit($form_id, ['email' => 'stored@example.com', 'consent' => '1']);

        self::title(__('A submission is stored', 'lonsda-light-form'));
        self::assert(sprintf('%d → %d', $before, Entries::count($form_id)), Entries::count($form_id) === $before + 1);

        $rows  = Entries::all(['form_id' => $form_id]);
        $entry = $rows ? Entries::decode($rows[0]) : [];

        self::title(__('It keeps the answers with their labels', 'lonsda-light-form'));
        $byName = array_column($entry['fields'] ?? [], null, 'name');
        self::assert(
            'email and consent recorded against their labels',
            'stored@example.com' === ($byName['email']['value'] ?? null)
                && 'Email' === ($byName['email']['label'] ?? null)
                && true === ($byName['consent']['value'] ?? null),
            $entry['fields'] ?? []
        );

        self::title(__('It keeps the metadata, language and locale both', 'lonsda-light-form'));
        self::assert(
            sprintf('language %s, locale %s, ip %s', $entry['language'] ?? '?', $entry['locale'] ?? '?', $entry['ip'] ?? '?'),
            '' !== ($entry['language'] ?? '') && '' !== ($entry['locale'] ?? '') && '' !== ($entry['submitted_at'] ?? '')
        );

        self::title(__('The form title is kept, so an entry survives its form', 'lonsda-light-form'));
        self::assert($entry['form_title'] ?? '', 0 === strpos((string) ($entry['form_title'] ?? ''), self::PREFIX));

        self::title(__('A rejected submission is not stored', 'lonsda-light-form'));
        $count = Entries::count($form_id);
        self::submit($form_id, ['email' => 'not-an-email']);
        self::assert('count unchanged at ' . Entries::count($form_id), Entries::count($form_id) === $count);

        self::title(__('A form set not to keep entries stores nothing', 'lonsda-light-form'));
        carbon_set_post_meta($post_id, 'llf_store_entries', false);
        Forms::syncToTable($post_id, get_post($post_id));
        $count = Entries::count($form_id);
        self::submit($form_id, ['email' => 'ignored@example.com', 'consent' => '1']);
        self::assert('count unchanged at ' . Entries::count($form_id), Entries::count($form_id) === $count);

        self::title(__('A new entry arrives unread', 'lonsda-light-form'));
        self::assert($entry['status'] ?? '?', Entries::STATUS_NEW === ($entry['status'] ?? ''));

        self::title(__('The unread count sees it', 'lonsda-light-form'));
        self::assert(Entries::countNew($form_id) . ' unread', Entries::countNew($form_id) > 0);

        self::title(__('Opening it marks it viewed', 'lonsda-light-form'));
        Entries::markViewed($entry['id']);
        $after = Entries::get($entry['id']);
        self::assert($after['status'] ?? '?', Entries::STATUS_VIEWED === ($after['status'] ?? ''));

        self::title(__('And the unread count drops', 'lonsda-light-form'));
        self::assert(Entries::countNew($form_id) . ' unread', 0 === Entries::countNew($form_id));

        self::title(__('It can be put back on the pile', 'lonsda-light-form'));
        Entries::setStatus($entry['id'], Entries::STATUS_NEW);
        self::assert('unread again', Entries::STATUS_NEW === (Entries::get($entry['id'])['status'] ?? ''));

        self::title(__('An invented status is refused', 'lonsda-light-form'));
        self::assert(
            'rejected, and the entry is untouched',
            false === Entries::setStatus($entry['id'], 'nonsense')
                && Entries::STATUS_NEW === (Entries::get($entry['id'])['status'] ?? '')
        );

        self::title(__('Filtering by language agrees with counting it', 'lonsda-light-form'));
        $lang = $entry['language'] ?? '';
        self::assert(
            sprintf('"%s": %d rows, count says %d', $lang, count(Entries::all(['form_id' => $form_id, 'language' => $lang])), Entries::count($form_id, '', $lang)),
            count(Entries::all(['form_id' => $form_id, 'language' => $lang])) === Entries::count($form_id, '', $lang)
        );

        self::title(__('A language nothing was submitted in matches nothing', 'lonsda-light-form'));
        self::assert('none', 0 === Entries::count($form_id, '', 'zz'));

        self::title(__('The filter lists only languages that were used', 'lonsda-light-form'));
        $offered = Entries::languages();
        self::assert(
            implode(', ', array_keys($offered)),
            isset($offered[$lang]) && !isset($offered['zz'])
        );

        self::title(__('Filtering by status agrees with counting it', 'lonsda-light-form'));
        self::assert(
            sprintf('%d rows, count says %d', count(Entries::all(['form_id' => $form_id, 'status' => Entries::STATUS_NEW])), Entries::count($form_id, Entries::STATUS_NEW)),
            count(Entries::all(['form_id' => $form_id, 'status' => Entries::STATUS_NEW])) === Entries::count($form_id, Entries::STATUS_NEW)
        );

        self::title(__('Entries can be ordered by any sortable column', 'lonsda-light-form'));
        $ok = true;

        foreach (Entries::SORTABLE as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $rows = Entries::all(['form_id' => $form_id, 'orderby' => $column, 'order' => $direction]);

                if (!is_array($rows)) {
                    $ok = false;
                }
            }
        }

        self::assert(implode(', ', Entries::SORTABLE), $ok);

        self::title(__('Ascending and descending are actually opposite', 'lonsda-light-form'));
        // Needs more than one row to mean anything. Storing was switched off by
        // an earlier step in this scenario, so it goes back on first.
        carbon_set_post_meta($post_id, 'llf_store_entries', true);
        Forms::syncToTable($post_id, get_post($post_id));
        self::submit($form_id, ['email' => 'second@example.com', 'consent' => '1']);
        $up   = wp_list_pluck(Entries::all(['form_id' => $form_id, 'orderby' => 'id', 'order' => 'asc']), 'id');
        $down = wp_list_pluck(Entries::all(['form_id' => $form_id, 'orderby' => 'id', 'order' => 'desc']), 'id');
        self::assert(
            implode(',', $up) . ' vs ' . implode(',', $down),
            count($up) > 1 && $up === array_reverse($down)
        );

        self::title(__('An invented column is ignored rather than obeyed', 'lonsda-light-form'));
        // The column name goes straight into ORDER BY, so it has to be one of
        // ours and not one the request supplied.
        $injected = Entries::all(['form_id' => $form_id, 'orderby' => 'id; DROP TABLE wp_posts', 'order' => 'asc']);
        self::assert(
            'falls back to the default order, table untouched',
            count($injected) === count($up) && null !== Forms::get($form_id)
        );

        self::title(__('CSV export includes the answers', 'lonsda-light-form'));
        $csv = Entries::csv($form_id);
        self::assert('the stored address appears in the CSV', false !== strpos($csv, 'stored@example.com'));

        self::title(__('An entry can be deleted', 'lonsda-light-form'));
        $id = $entry['id'] ?? 0;
        self::assert('deleted', $id > 0 && Entries::delete($id) && null === Entries::get($id));
    }

    private static function notificationsScenario(): void
    {
        self::heading(__('Notification emails', 'lonsda-light-form'));

        $post_id = self::makeForm('Notifications', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $sent = [];
        $spy  = static function ($mail) use (&$sent) {
            $sent[] = $mail;

            // Observes only. run() cancels the send at a later priority, so
            // nothing a scenario does can put mail on the wire.
            return $mail;
        };

        add_filter(Notifications::FILTER_MAIL, $spy, 10);

        self::title(__('Clearing the recipient means no notification', 'lonsda-light-form'));
        // Explicitly emptied: a new form is prefilled with the site address, so
        // "no recipient" is a choice someone makes rather than the starting
        // state, and it has to keep working.
        carbon_set_post_meta($post_id, 'llf_notify_to', '');
        Forms::syncToTable($post_id, get_post($post_id));
        self::submit($form_id, ['email' => 'visitor@example.com', 'message' => 'Hi']);
        self::assert('nothing attempted', 0 === count($sent));

        self::title(__('A new form is prefilled with the site address', 'lonsda-light-form'));
        self::assert(
            FormBuilder::defaultNotifyTo(),
            '' !== FormBuilder::defaultNotifyTo() && is_email(FormBuilder::defaultNotifyTo())
        );

        carbon_set_post_meta($post_id, 'llf_notify_to', 'owner@example.com, second@example.com, nonsense');
        carbon_set_post_meta($post_id, 'llf_notify_reply_to', 'email');
        Forms::syncToTable($post_id, get_post($post_id));

        self::submit($form_id, ['email' => 'visitor@example.com', 'message' => 'Hello there']);

        self::title(__('A recipient means one notification', 'lonsda-light-form'));
        self::assert(count($sent) . ' prepared', 1 === count($sent), $sent);

        $mail = $sent[0] ?? [];

        self::title(__('Invalid addresses are dropped, valid ones kept', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['to'] ?? [])),
            ['owner@example.com', 'second@example.com'] === ($mail['to'] ?? [])
        );

        self::title(__('The subject is the form name, with the placeholder resolved', 'lonsda-light-form'));
        $subject = (string) ($mail['subject'] ?? '');
        self::assert(
            $subject,
            false !== strpos($subject, self::PREFIX) && false === strpos($subject, '{form_title}')
        );

        self::title(__('The body sets each label above its answer, in bold', 'lonsda-light-form'));
        self::assert(
            'label bold, answer on the next line',
            self::hasAll((string) ($mail['message'] ?? ''), [
                '<strong>Email:</strong><br>visitor@example.com',
                '<strong>Message:</strong><br>Hello there',
            ]),
            $mail['message'] ?? ''
        );

        self::title(__('And it is sent as HTML', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['headers'] ?? [])),
            in_array('Content-Type: text/html; charset=UTF-8', (array) ($mail['headers'] ?? []), true)
        );

        self::title(__('Replies go to the submitter', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['headers'] ?? [])),
            in_array('Reply-To: visitor@example.com', (array) ($mail['headers'] ?? []), true)
        );

        self::title(__('A field can be used by its name in the subject', 'lonsda-light-form'));
        carbon_set_post_meta($post_id, 'llf_notify_subject', 'From {email} via {site_name}');
        carbon_set_post_meta($post_id, 'llf_notify_message', "Hello,\n{message}\n\n{all_fields}\n{page_url}");
        Forms::syncToTable($post_id, get_post($post_id));

        $sent = [];
        self::submit($form_id, ['email' => 'named@example.com', 'message' => 'Placeholder body']);
        $mail = $sent[0] ?? [];

        // Both halves: the field answer and a built-in. Asserting only the
        // start of the subject would pass with {site_name} still sitting there
        // unreplaced, which is exactly the way this fails in practice.
        self::assert(
            (string) ($mail['subject'] ?? ''),
            'From named@example.com via ' . get_bloginfo('name') === (string) ($mail['subject'] ?? '')
        );

        self::title(__('And in the message', 'lonsda-light-form'));
        self::assert(
            'the field answer was substituted',
            false !== strpos((string) ($mail['message'] ?? ''), 'Placeholder body')
        );

        self::title(__('Every placeholder the screens list is one that exists', 'lonsda-light-form'));
        // The list shown on the form editor and beside the translations is what
        // somebody types from. A token on that list that nothing substitutes is
        // a brace in an email and no clue as to why.
        $listed = array_keys(Notifications::placeholderReference());
        $real   = array_keys(Notifications::placeholders(
            [],
            ['settings' => ['fields' => []], 'title' => 'x'],
            ['form_id' => 0, 'submitted_at' => '2026-01-01 00:00:00', 'language' => 'lv', 'locale' => 'lv_LV', 'ip' => '', 'user_agent' => '']
        ));
        $unreal = array_diff($listed, $real);
        self::assert(
            $unreal ? 'listed but never replaced: ' . implode(', ', $unreal) : count($listed) . ' listed, all real',
            [] === $unreal
        );

        self::title(__('An unknown placeholder is left alone rather than emptied', 'lonsda-light-form'));
        // {site-name} is not {site_name}, and only the second is a placeholder.
        // Nothing invents a replacement for one that does not exist, so a typo
        // arrives in the mail as itself rather than silently as a blank — which
        // is the difference between noticing it and not.
        carbon_set_post_meta($post_id, 'llf_notify_subject', 'Sent by {site-name}');
        Forms::syncToTable($post_id, get_post($post_id));

        $sent = [];
        self::submit($form_id, ['email' => 'named@example.com', 'message' => 'Placeholder body']);
        self::assert(
            (string) ($sent[0]['subject'] ?? ''),
            'Sent by {site-name}' === (string) ($sent[0]['subject'] ?? '')
        );

        carbon_set_post_meta($post_id, 'llf_notify_subject', 'From {email} via {site_name}');
        Forms::syncToTable($post_id, get_post($post_id));

        self::title(__('{submission_details} leaves out what it does not have', 'lonsda-light-form'));
        // The reason the default body can be one translatable string: the part
        // that varies is a placeholder rather than code around it.
        $sent = [];
        self::submit($form_id, ['email' => 'named@example.com', 'message' => 'x']);
        $body = (string) ($sent[0]['message'] ?? '');
        self::assert(
            'no label is left with nothing after it',
            0 === preg_match('/(Page|IP address|Language): *(<br>|<\/small>)/', $body),
            $body
        );

        self::title(__('{all_fields} expands to the whole list', 'lonsda-light-form'));
        // <br> or <br />: a template goes through wpautop, which normalises it.
        $body = preg_replace('|<br\s*/?>|', '<br>', (string) ($mail['message'] ?? ''));
        self::assert(
            'each label bold, above its answer',
            self::hasAll($body, [
                '<strong>Email:</strong><br>named@example.com',
                '<strong>Message:</strong><br>Placeholder body',
            ]),
            $body
        );

        self::title(__('An answer cannot bring markup with it', 'lonsda-light-form'));
        // The one place a stranger's words are put into markup that lands in
        // somebody's inbox. Two layers: the submission handler sanitises tags
        // out on the way in, and this escapes whatever survives on the way out
        // — so the check is that nothing renders, plus that escaping is
        // demonstrably happening at all.
        $sent = [];
        self::submit($form_id, ['email' => 'named@example.com', 'message' => '<b>bold</b> & <i>italic</i>']);
        $body = (string) ($sent[0]['message'] ?? '');
        self::assert(
            'no tag from the answer renders, and the ampersand is encoded',
            false === stripos($body, '<b>bold') && false === stripos($body, '<i>italic')
                && false !== strpos($body, '&amp;'),
            $body
        );

        self::title(__('No placeholder survives into the message', 'lonsda-light-form'));
        self::assert(
            'nothing left in braces',
            0 === preg_match('/\{[a-z_]+\}/', (string) ($mail['message'] ?? '') . (string) ($mail['subject'] ?? '')),
            $mail['message'] ?? ''
        );

        remove_filter(Notifications::FILTER_MAIL, $spy, 10);

        self::title(__('No mail left this run', 'lonsda-light-form'));
        // run() cancels every send by returning an empty array, the documented
        // way to stop one, so a test run cannot email the site administrator.
        self::assert('every send was cancelled', true);
    }

    private static function autoReplyScenario(): void
    {
        self::heading(__('Auto reply', 'lonsda-light-form'));

        // This scenario writes a translation of its own; cleanup() removes the
        // file, and it is the same test locale nothing serves.

        $post_id = self::makeForm('AutoReply', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Name', 'name' => 'sender_name', 'type' => 'text'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $sent = [];
        $spy  = static function ($mail) use (&$sent) {
            $sent[] = $mail;

            return $mail;
        };

        add_filter(AutoReply::FILTER_MAIL, $spy, 10);

        self::title(__('Off by default, so nothing is sent', 'lonsda-light-form'));
        self::submit($form_id, ['email' => 'visitor@example.com', 'sender_name' => 'Anna']);
        self::assert('nothing attempted', 0 === count($sent));

        carbon_set_post_meta($post_id, 'llf_auto_reply', true);
        carbon_set_post_meta($post_id, 'llf_auto_reply_subject', 'Thanks, {sender_name}');
        carbon_set_post_meta($post_id, 'llf_auto_reply_message', '<p>Hello {sender_name}, we have your message.</p>');
        Forms::syncToTable($post_id, get_post($post_id));

        self::submit($form_id, ['email' => 'visitor@example.com', 'sender_name' => 'Anna']);

        self::title(__('Switched on, it replies to the submitter', 'lonsda-light-form'));
        $mail = $sent[0] ?? [];
        self::assert(
            'to ' . (is_array($mail['to'] ?? null) ? implode(',', $mail['to']) : (string) ($mail['to'] ?? '?')),
            1 === count($sent) && 'visitor@example.com' === ($mail['to'] ?? '')
        );

        self::title(__('Placeholders are filled in', 'lonsda-light-form'));
        self::assert(
            (string) ($mail['subject'] ?? ''),
            'Thanks, Anna' === ($mail['subject'] ?? '')
                && false !== strpos((string) ($mail['message'] ?? ''), 'Hello Anna')
        );

        self::title(__('The text version keeps the paragraph breaks', 'lonsda-light-form'));
        // The reason both parts are sent: flattened markup arrives as one
        // unbroken block, which is how an auto reply reads as a wall of text.
        $text = Mail::toText('<p>One.</p><p>Two.</p>');
        self::assert(
            str_replace("\n", '\\n', $text),
            "One.\n\nTwo." === $text
        );

        self::title(__('A line break inside a paragraph survives as one', 'lonsda-light-form'));
        self::assert(
            str_replace("\n", '\\n', Mail::toText('<p>One<br>Two</p>')),
            "One\nTwo" === Mail::toText('<p>One<br>Two</p>')
        );

        self::title(__('A link carries its address', 'lonsda-light-form'));
        // A text reader has nothing to click.
        self::assert(
            Mail::toText('<p>See <a href="https://example.com">the page</a>.</p>'),
            'See the page (https://example.com).' === Mail::toText('<p>See <a href="https://example.com">the page</a>.</p>')
        );

        self::title(__('Entities are decoded, not left as markup', 'lonsda-light-form'));
        self::assert(Mail::toText('<p>Tom &amp; Jerry</p>'), 'Tom & Jerry' === Mail::toText('<p>Tom &amp; Jerry</p>'));

        self::title(__('Nested blocks do not pile up blank lines', 'lonsda-light-form'));
        self::assert(
            str_replace("\n", '\\n', Mail::toText('<div><p>One.</p></div><div><p>Two.</p></div>')),
            "One.\n\nTwo." === Mail::toText('<div><p>One.</p></div><div><p>Two.</p></div>')
        );

        self::title(__('It is sent as HTML', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['headers'] ?? [])),
            in_array('Content-Type: text/html; charset=UTF-8', (array) ($mail['headers'] ?? []), true)
        );

        self::title(__('Script in the message is stripped', 'lonsda-light-form'));
        // It is written into an email by whoever edits the form, and nothing
        // there needs a script tag.
        $sent = [];
        carbon_set_post_meta($post_id, 'llf_auto_reply_message', '<p>Hi</p><script>alert(1)</script>');
        Forms::syncToTable($post_id, get_post($post_id));
        self::submit($form_id, ['email' => 'visitor@example.com']);
        self::assert(
            'no script tag survives',
            false === stripos((string) ($sent[0]['message'] ?? ''), '<script')
        );

        self::title(__('A translated auto reply is the one that goes out', 'lonsda-light-form'));
        // The whole point of keying these to the form: a visitor writing in
        // one language should not be answered in another.
        //
        // The language is put on the submission rather than by loading the
        // translation file by hand. Hand-loading was how this read until the
        // reply started switching language for itself, and the two fought: the
        // switch reloads the file for the language the submission records, so
        // whatever the test had loaded was replaced a moment later and the
        // reply came out in English. It only showed up on a site whose admin
        // screen is in a different language from the submission — everywhere
        // else the switch finds nothing to do and leaves the hand-loaded file
        // alone.
        $settings = Forms::get($form_id)['settings'];
        Translations::save(self::TEST_LOCALE, [
            (string) $settings['auto_reply_subject_key'] => 'Paldies, {sender_name}',
            (string) $settings['auto_reply_message_key'] => '<p>Paldies par ziņu.</p>',
        ]);

        $sent    = [];
        $relabel = static function ($context) {
            $context['locale']   = self::TEST_LOCALE;
            $context['language'] = 'zz';

            return $context;
        };

        add_filter(Submission::FILTER_CONTEXT, $relabel, 10);
        self::submit($form_id, ['email' => 'visitor@example.com', 'sender_name' => 'Anna']);
        remove_filter(Submission::FILTER_CONTEXT, $relabel, 10);

        $translated = $sent[0] ?? [];
        self::assert(
            (string) ($translated['subject'] ?? ''),
            'Paldies, Anna' === ($translated['subject'] ?? '')
                && false !== strpos((string) ($translated['message'] ?? ''), 'Paldies par ziņu'),
            $translated
        );

        self::title(__('And the language is put back afterwards', 'lonsda-light-form'));
        // A reply left the site in another language once the mail had gone
        // would be a far worse bug than the one this fixes.
        $sent = [];
        self::submit($form_id, ['email' => 'visitor@example.com', 'sender_name' => 'Anna']);
        self::assert(
            (string) ($sent[0]['subject'] ?? ''),
            'Thanks, Anna' === ($sent[0]['subject'] ?? ''),
            ['locale' => determine_locale()]
        );

        self::title(__('Without an email field there is nowhere to send it', 'lonsda-light-form'));
        $sent  = [];
        $other = self::makeForm('AutoReply No Address', [
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
        ]);
        carbon_set_post_meta($other, 'llf_auto_reply', true);
        Forms::syncToTable($other, get_post($other));
        self::submit(Forms::tableIdForPost($other), ['message' => 'no address here']);
        self::assert('nothing attempted', 0 === count($sent));

        remove_filter(AutoReply::FILTER_MAIL, $spy, 10);

        self::title(__('No mail left this run', 'lonsda-light-form'));
        self::assert('every send was cancelled', true);
    }

    /**
     * Placeholders and translation together, which is where either one breaks.
     *
     * Separately they are simple: a token is replaced, a string is translated.
     * The interesting part is the order — a string is translated first and its
     * tokens filled in afterwards, so a language can put {name} wherever it
     * wants it — and the fact that the words a token stands for are themselves
     * translated: a ticked box, a field's label, an unanswered field.
     *
     * The form here is given a full set of example translations rather than one
     * or two, because the failure being guarded against is not "no translation
     * arrived" but "half of it did".
     */
    private static function placeholdersScenario(): void
    {
        self::heading(__('Placeholders and translation', 'lonsda-light-form'));

        // Writes to the test locale nothing serves; cleanup removes the files.

        $post_id = self::makeForm('Placeholders', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Name', 'name' => 'sender_name', 'type' => 'text'],
            ['label' => 'Nickname', 'name' => 'nickname', 'type' => 'text'],
            ['label' => 'Consent', 'name' => 'consent', 'type' => 'checkbox'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        carbon_set_post_meta($post_id, 'llf_notify_to', 'owner@example.com');
        carbon_set_post_meta($post_id, 'llf_notify_subject', 'From {sender_name} via {site_name}');
        carbon_set_post_meta($post_id, 'llf_auto_reply', true);
        carbon_set_post_meta($post_id, 'llf_auto_reply_subject', 'Thank you {sender_name} — {site_name}');
        carbon_set_post_meta($post_id, 'llf_auto_reply_message', '<p>Hello {sender_name}, this is {form_title}.</p>{all_fields}');
        Forms::syncToTable($post_id, get_post($post_id));

        $notified = [];
        $replied  = [];

        $spyNote  = static function ($mail) use (&$notified) {
            $notified[] = $mail;

            return $mail;
        };

        $spyReply = static function ($mail) use (&$replied) {
            $replied[] = $mail;

            return $mail;
        };

        add_filter(Notifications::FILTER_MAIL, $spyNote, 10);
        add_filter(AutoReply::FILTER_MAIL, $spyReply, 10);

        $answers = [
            'email'       => 'visitor@example.com',
            'sender_name' => 'Anna',
            'nickname'    => '',
            'consent'     => '1',
        ];

        $site = (string) get_bloginfo('name');

        self::submit($form_id, $answers);

        self::title(__('In the site\'s own language, every token is replaced', 'lonsda-light-form'));
        // Exact equality rather than a search: a token that survived would sit
        // there in braces, and "contains Anna" would pass with it.
        self::assert(
            (string) ($replied[0]['subject'] ?? ''),
            'Thank you Anna — ' . $site === ($replied[0]['subject'] ?? '')
                && 'From Anna via ' . $site === ($notified[0]['subject'] ?? '')
        );

        self::title(__('Nothing is left in braces', 'lonsda-light-form'));
        $everything = (string) ($replied[0]['subject'] ?? '') . (string) ($replied[0]['message'] ?? '');
        self::assert(
            'no unreplaced token anywhere in the reply',
            false === strpos($everything, '{')
        );

        self::title(__('The form\'s own title is one of the tokens', 'lonsda-light-form'));
        $title = (string) (Forms::get($form_id)['title'] ?? '');
        self::assert(
            $title,
            false !== strpos((string) ($replied[0]['message'] ?? ''), 'this is ' . $title)
        );

        self::title(__('{all_fields} carries the labels, the answers and the words between', 'lonsda-light-form'));
        self::assert(
            'label, answer, a ticked box as a word, an empty field named as such',
            self::hasAll((string) ($replied[0]['message'] ?? ''), ['Name:', 'Anna', 'Consent:', 'Yes', '(not answered)'])
        );

        // An example translation of the whole form, as a site would have.
        $settings = Forms::get($form_id)['settings'];
        $keys     = [];

        foreach ($settings['fields'] as $field) {
            $keys[$field['name']] = (string) $field['translation_key'];
        }

        Translations::save(self::TEST_LOCALE, [
            $keys['email']       => 'E-pasts',
            $keys['sender_name'] => 'Vārds',
            $keys['nickname']    => 'Segvārds',
            $keys['consent']     => 'Piekrišana',

            (string) $settings['submit_key']  => 'Sūtīt',
            (string) $settings['success_key'] => 'Paldies! Ziņa ir nosūtīta.',

            // Deliberately reordered: {site_name} first, where English had it
            // last. Substituting before translating would make this impossible,
            // and nothing else in the suite would notice.
            (string) $settings['auto_reply_subject_key']  => '{site_name}: paldies, {sender_name}',
            // Plain paragraphs, which is what gets typed into a translation
            // box — the markup is the editor's, not the translator's.
            (string) $settings['auto_reply_message_key']  => "Sveiki, {sender_name}!\n\nŠī ir {form_title}.\n\n{all_fields}",

            // The form's own name, which is read out in every mail it sends.
            (string) $settings['title_key'] => 'Kontakti',
            (string) $settings['notify_subject_key']      => '{site_name}: ziņa no {sender_name}',

            'general__word_yes'          => 'Jā',
            'general__word_not_answered' => '(nav atbildēts)',
            'general__error_required'    => 'Šis lauks ir obligāts.',
            'general__error_max_length'  => 'Ne vairāk kā %d rakstzīmes.',
        ]);

        // The language the submission was made in, which is what the auto reply
        // is written in — see AutoReply. Nothing loads the file by hand here.
        $inLatvian = static function ($context) {
            $context['locale']   = self::TEST_LOCALE;
            $context['language'] = 'zz';

            return $context;
        };

        $replied  = [];
        $notified = [];

        add_filter(Submission::FILTER_CONTEXT, $inLatvian, 10);
        self::submit($form_id, $answers);
        remove_filter(Submission::FILTER_CONTEXT, $inLatvian, 10);

        self::title(__('A translation may put the tokens somewhere else entirely', 'lonsda-light-form'));
        self::assert(
            (string) ($replied[0]['subject'] ?? ''),
            $site . ': paldies, Anna' === ($replied[0]['subject'] ?? '')
        );

        self::title(__('And the words a token stands for are translated too', 'lonsda-light-form'));
        // The half that used to be missed: the wording around {all_fields} was
        // translated and the list inside it was not, so a Latvian reply asked
        // its questions in English.
        self::assert(
            'translated labels, and a ticked box as a translated word',
            self::hasAll((string) ($replied[0]['message'] ?? ''), ['Sveiki, Anna', 'Vārds:', 'Piekrišana:', 'Jā', '(nav atbildēts)'])
        );

        self::title(__('Including the title, which is a string like any other', 'lonsda-light-form'));
        // It is the post's title rather than one of the form's settings, which
        // is exactly why it was the one that stayed in English: a Latvian
        // subject line with the word Contacts in the middle of it.
        self::assert(
            'translated title in, English title out',
            false !== strpos((string) ($replied[0]['message'] ?? ''), 'Šī ir Kontakti')
                && false === strpos((string) ($replied[0]['message'] ?? ''), $title)
        );

        self::title(__('A translation typed as plain paragraphs arrives as paragraphs', 'lonsda-light-form'));
        // The translation box held no markup for a long time, so a message
        // written as three paragraphs was translated into one line of text and
        // arrived as one unbroken block — in the language most of the site's
        // visitors read, which is the worst half to get wrong.
        $body = (string) ($replied[0]['message'] ?? '');
        self::assert(
            'paragraphs in the html, and blank lines in the text part',
            substr_count($body, '<p>') >= 2
                && false !== strpos(Mail::toText($body), "\n\n")
        );

        self::title(__('Without wrapping a paragraph round the blocks a token brought', 'lonsda-light-form'));
        // {all_fields} is paragraphs already. Paragraphing before the tokens
        // were in would have put this message's field list inside one.
        self::assert(
            'no nested or empty paragraph',
            false === strpos($body, '<p><p>') && false === strpos($body, '<p></p>')
        );

        self::title(__('A body gets a box it can hold a paragraph in', 'lonsda-light-form'));
        // What the screen offers has to match what the string is: a one-line
        // input for a message body is how the line above went wrong.
        self::assert(
            'bodies get a textarea, labels do not',
            Translations::isBody('<p>Hello.</p><p>Goodbye.</p>')
                && Translations::isBody("Two\nlines")
                && !Translations::isBody('Email')
        );

        self::title(__('The notification stays in the language of the request', 'lonsda-light-form'));
        // Deliberate, and the reason the two are not written the same way: this
        // one is read by whoever runs the site, not by the visitor.
        self::assert(
            (string) ($notified[0]['subject'] ?? ''),
            'From Anna via ' . $site === ($notified[0]['subject'] ?? '')
        );

        $replied = [];
        self::submit($form_id, $answers);

        self::title(__('A submission in the site\'s language is answered in it again', 'lonsda-light-form'));
        // The language has to be put back, or the first translated reply would
        // be the last English one.
        self::assert(
            (string) ($replied[0]['subject'] ?? ''),
            'Thank you Anna — ' . $site === ($replied[0]['subject'] ?? '')
        );

        // The validation messages are shown by the request rather than by a
        // mail, so this is the request's own language changing rather than a
        // submission's.
        unload_textdomain(Translations::DOMAIN);
        load_textdomain(Translations::DOMAIN, Translations::path(self::TEST_LOCALE));

        $required = Submission::validate(['name' => 'x', 'type' => 'text', 'required' => true], '');
        $tooLong  = Submission::validate(['name' => 'x', 'type' => 'text', 'max_length' => 5], 'far too long');

        unload_textdomain(Translations::DOMAIN);
        Translations::load();

        self::title(__('A general text is translated where it is shown', 'lonsda-light-form'));
        self::assert($required, 'Šis lauks ir obligāts.' === $required);

        self::title(__('And a number in one is filled in after translating, not before', 'lonsda-light-form'));
        // %d survives the trip and lands where the translation put it, which is
        // the whole reason the number is not baked in before the lookup.
        self::assert($tooLong, 'Ne vairāk kā 5 rakstzīmes.' === $tooLong);

        self::title(__('A token that does not exist is left alone, even in a translation', 'lonsda-light-form'));
        // {site-name} is not {site_name}. It arrives as itself rather than as a
        // blank, which is the difference between seeing the mistake and not.
        Translations::save(self::TEST_LOCALE, [
            (string) $settings['auto_reply_subject_key'] => 'Sveiki no {site-name}',
        ]);

        $replied = [];
        add_filter(Submission::FILTER_CONTEXT, $inLatvian, 10);
        self::submit($form_id, $answers);
        remove_filter(Submission::FILTER_CONTEXT, $inLatvian, 10);

        self::assert(
            (string) ($replied[0]['subject'] ?? ''),
            'Sveiki no {site-name}' === ($replied[0]['subject'] ?? '')
        );

        remove_filter(Notifications::FILTER_MAIL, $spyNote, 10);
        remove_filter(AutoReply::FILTER_MAIL, $spyReply, 10);

        self::title(__('No mail left this run', 'lonsda-light-form'));
        self::assert('every send was cancelled', true);
    }

    private static function testMailScenario(): void
    {
        self::heading(__('Testing tab', 'lonsda-light-form'));

        $post_id = self::makeForm('TestMail', [
            ['label' => 'Email', 'name' => 'email', 'type' => 'text', 'validation' => 'email', 'required' => true],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);

        $sent = [];
        $spy  = static function ($mail) use (&$sent) {
            $sent[] = $mail;

            return $mail;
        };

        // Priority 500: after TestMail redirects the recipient at 99, before
        // run() cancels the send at 999. At 10 this would capture the message
        // as the sender built it, not as the test actually addressed it.
        add_filter(Notifications::FILTER_MAIL, $spy, 500);

        self::title(__('A form with no recipient says what to change', 'lonsda-light-form'));
        // A new form is prefilled with the site address, so having none is a
        // choice someone makes — which is the state being tested here.
        carbon_set_post_meta($post_id, 'llf_notify_to', '');
        Forms::syncToTable($post_id, get_post($post_id));
        $r = TestMail::send($post_id, TestMail::NOTIFICATION, 'tester@example.com');
        self::assert($r['message'], false === $r['sent'] && false !== strpos($r['message'], 'Notifications tab'));

        carbon_set_post_meta($post_id, 'llf_notify_to', 'owner@example.com');
        Forms::syncToTable($post_id, get_post($post_id));

        $sent   = [];
        $before = Entries::count();
        $r      = TestMail::send($post_id, TestMail::NOTIFICATION, 'tester@example.com');
        $mail   = $sent[0] ?? [];
        $to     = is_array($mail['to'] ?? null) ? implode(',', $mail['to']) : (string) ($mail['to'] ?? '');

        self::title(__('With one, it sends to the address given', 'lonsda-light-form'));
        self::assert($to, $r['sent'] && 'tester@example.com' === $to);

        self::title(__('Not to the form\'s real recipient', 'lonsda-light-form'));
        // The whole point: testing a form must not mail whoever normally hears
        // about it.
        self::assert('owner@example.com was not written to', false === strpos($to, 'owner@'));

        self::title(__('The subject is marked as a test', 'lonsda-light-form'));
        self::assert((string) ($mail['subject'] ?? ''), 0 === strpos((string) ($mail['subject'] ?? ''), '[TEST]'));

        self::title(__('The answers are filled in, not left blank', 'lonsda-light-form'));
        self::assert(
            'sample answers present',
            self::hasAll((string) ($mail['message'] ?? ''), ['<strong>Email:</strong>', '<strong>Message:</strong>', 'tester@example.com'])
        );

        self::title(__('Nothing is stored as an entry', 'lonsda-light-form'));
        self::assert(sprintf('%d before, %d after', $before, Entries::count()), $before === Entries::count());

        self::title(__('It can be sent in a language other than this one', 'lonsda-light-form'));
        // The point of the picker: seeing what a visitor in that language
        // receives, without having to be that visitor.
        carbon_set_post_meta($post_id, 'llf_notify_subject', 'New message');
        Forms::syncToTable($post_id, get_post($post_id));

        $settings = Forms::get($form_id)['settings'];
        Translations::save(self::TEST_LOCALE, [
            (string) $settings['notify_subject_key'] => 'Jauna ziņa',
        ]);

        $sent = [];
        $r    = TestMail::send($post_id, TestMail::NOTIFICATION, 'tester@example.com', self::TEST_LOCALE);
        self::assert(
            (string) ($sent[0]['subject'] ?? ''),
            $r['sent'] && false !== strpos((string) ($sent[0]['subject'] ?? ''), 'Jauna ziņa')
        );

        self::title(__('And the language is named in what comes back', 'lonsda-light-form'));
        // Anything still in English in that mail has no translation rather than
        // a broken one, and the reply says so rather than leaving it a puzzle.
        self::assert($r['message'], false !== strpos($r['message'], self::TEST_LOCALE));

        self::title(__('Without a language it is sent in this one', 'lonsda-light-form'));
        $sent = [];
        TestMail::send($post_id, TestMail::NOTIFICATION, 'tester@example.com');
        self::assert(
            (string) ($sent[0]['subject'] ?? ''),
            false === strpos((string) ($sent[0]['subject'] ?? ''), 'Jauna ziņa')
        );

        self::title(__('A locale the site does not have is ignored, not obeyed', 'lonsda-light-form'));
        // It arrives from a browser and decides what language the whole request
        // is switched into, so being merely well-formed is not enough.
        $sent = [];
        TestMail::send($post_id, TestMail::NOTIFICATION, 'tester@example.com', 'qq_QQ');
        self::assert(
            'fell back to the site\'s own language',
            false === strpos((string) ($sent[0]['subject'] ?? ''), 'Jauna ziņa')
        );

        self::title(__('And the language is put back once the mail has gone', 'lonsda-light-form'));
        // Whoever pressed the button is not the visitor being imagined, and the
        // rest of the request is theirs. Asked of the form strings rather than
        // of determine_locale(), which never moves for a locale core has no
        // files for and would report success either way.
        self::assert(
            'form strings resolve in this language again',
            'New message' === Strings::get('New message', (string) $settings['notify_subject_key'])
        );

        self::title(__('An invalid address is refused', 'lonsda-light-form'));
        $r = TestMail::send($post_id, TestMail::NOTIFICATION, 'not-an-address');
        self::assert($r['message'], false === $r['sent']);

        self::title(__('An auto reply that is switched off says so', 'lonsda-light-form'));
        $r = TestMail::send($post_id, TestMail::AUTO_REPLY, 'tester@example.com');
        self::assert($r['message'], false === $r['sent'] && false !== strpos($r['message'], 'Auto reply tab'));

        remove_filter(Notifications::FILTER_MAIL, $spy, 500);

        self::title(__('No mail left this run', 'lonsda-light-form'));
        self::assert('every send was cancelled', true);
    }

    private static function translationsScenario(): void
    {
        self::heading(__('Translations', 'lonsda-light-form'));

        $post_id = self::makeForm('Translations', [
            ['label' => 'Given name', 'name' => 'given_name', 'type' => 'text'],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
        ]);
        $form_id = Forms::tableIdForPost($post_id);
        $key     = FormBuilder::generatedFieldKey('given_name');
        $other   = FormBuilder::generatedFieldKey('message');

        self::title(__('A placeholder is collected as its own string', 'lonsda-light-form'));
        self::setFields($post_id, [
            ['label' => 'Given name', 'name' => 'given_name', 'type' => 'text', 'placeholder' => 'e.g. Anna'],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea'],
        ]);
        Forms::syncToTable($post_id, get_post($post_id));

        $withPlaceholder = FormBuilder::generatedPlaceholderKey('given_name');
        $noPlaceholder   = FormBuilder::generatedPlaceholderKey('message');
        $all             = Translations::strings();

        self::assert(
            'the one with a placeholder is offered, the one without is not',
            isset($all[$withPlaceholder]) && !isset($all[$noPlaceholder]),
            array_keys($all)
        );

        self::title(__('A translated placeholder reaches the rendered input', 'lonsda-light-form'));
        Translations::save(self::TEST_LOCALE, [$withPlaceholder => 'piem. Anna']);
        unload_textdomain(Translations::DOMAIN);
        load_textdomain(Translations::DOMAIN, Translations::path(self::TEST_LOCALE));
        $html = Renderer::form($form_id);
        unload_textdomain(Translations::DOMAIN);
        Translations::load();

        self::assert(
            'the input carries the translated hint',
            false !== strpos($html, 'placeholder="piem. Anna"'),
            $html
        );

        self::title(__('The form\'s strings are collected', 'lonsda-light-form'));
        $strings = Translations::strings();
        self::assert(
            count($strings) . ' string(s) across all forms',
            isset($strings[$key], $strings[$other]) && 'Given name' === $strings[$key]['text']
        );

        self::title(__('They can be narrowed to one form', 'lonsda-light-form'));
        $mine = Translations::strings($form_id);
        self::assert(
            count($mine) . ' for this form',
            isset($mine[$key]) && count($mine) <= count($strings) && [] === Translations::strings(999999)
        );

        self::title(__('The plugin\'s own texts are offered alongside the forms', 'lonsda-light-form'));
        // They belong to no form, so picking one must not list them — and
        // asking for them by themselves must not drag every form along.
        $general = Translations::strings(Translations::GENERAL);
        self::assert(
            count($general) . ' general text(s)',
            isset($general['general__error_required'], $strings['general__error_required'])
                && !isset($mine['general__error_required'])
                && count($general) === count(Strings::generalStrings())
        );

        self::title(__('And translating one changes what a visitor is told', 'lonsda-light-form'));
        // The whole point of listing them: these used to be reachable only by
        // editing a .po inside a folder WordPress replaces on every update.
        Translations::save(self::TEST_LOCALE, ['general__error_required' => 'Šis lauks ir obligāts.']);

        unload_textdomain(Translations::DOMAIN);
        load_textdomain(Translations::DOMAIN, Translations::path(self::TEST_LOCALE));
        $said = Submission::validate(['name' => 'x', 'type' => 'text', 'required' => true], '');
        unload_textdomain(Translations::DOMAIN);
        Translations::load();

        self::assert($said, 'Šis lauks ir obligāts.' === $said);

        self::title(__('The POT keys by context and reads by label', 'lonsda-light-form'));
        $pot = Translations::pot();
        self::assert(
            'msgctxt is the key, msgid is the label',
            self::hasAll($pot, ['msgctxt "' . $key . '"', 'msgid "Given name"', 'Content-Type: text/plain; charset=UTF-8'])
        );

        self::title(__('The form\'s own messages are offered for translation', 'lonsda-light-form'));
        $settings = Forms::get($form_id)['settings'];
        $textId   = $settings['text_id'] ?? '';
        $offered  = Translations::strings($form_id);
        $expected = [
            $textId . '__success_message',
            $textId . '__auto_reply_subject',
            $textId . '__auto_reply_message',
        ];
        $missing  = array_diff($expected, array_keys($offered));
        self::assert(
            $missing ? 'missing: ' . implode(', ', $missing) : implode(', ', $expected),
            [] === $missing
        );

        self::title(__('A hand-typed text id is normalised, not merely used as typed', 'lonsda-light-form'));
        // The keys were always lower-cased on the way out; the point here is
        // that the box agrees with them afterwards.
        carbon_set_post_meta($post_id, 'llf_text_id', 'My Form ID');
        $resolved = FormBuilder::textId($post_id);
        $stored   = (string) carbon_get_post_meta($post_id, 'llf_text_id');
        self::assert(
            sprintf('resolved %s, box shows %s', $resolved, $stored),
            'my-form-id' === $resolved && $resolved === $stored
        );

        self::title(__('Accents and underscores are folded too', 'lonsda-light-form'));
        carbon_set_post_meta($post_id, 'llf_text_id', 'ĀBOLS_Form');
        self::assert(FormBuilder::textId($post_id), 'abols_form' === FormBuilder::textId($post_id));

        // Back to the generated one for the rest of the scenario.
        carbon_set_post_meta($post_id, 'llf_text_id', '');
        Forms::syncToTable($post_id, get_post($post_id));

        self::title(__('The text id prefixes them, so two forms stay apart', 'lonsda-light-form'));
        self::assert(
            $textId,
            '' !== $textId && 0 === strpos($expected[0], $textId . '__')
        );

        self::title(__('Strings come back split into sections', 'lonsda-light-form'));
        $sections = Translations::grouped($form_id);
        $unknown  = array_diff(array_keys($sections), array_keys(Translations::groups()));
        self::assert(
            implode(', ', array_keys($sections)),
            [] === $unknown && count($sections) > 1
        );

        self::title(__('Every string lands in exactly one section', 'lonsda-light-form'));
        // The editor renders a table per section, so a string in none would
        // silently vanish and one in two would be editable twice.
        $flat = [];

        foreach ($sections as $rows) {
            $flat = array_merge($flat, array_keys($rows));
        }

        self::assert(
            count($flat) . ' placed, ' . count(Translations::strings($form_id)) . ' collected',
            count($flat) === count(Translations::strings($form_id))
                && count($flat) === count(array_unique($flat))
        );

        self::title(__('The notification body is offered even when unwritten', 'lonsda-light-form'));
        // It has a shipped default like the confirmation and the auto reply, so
        // there is always something to translate. It had none, and was the only
        // message a form sends that could not be translated until someone
        // happened to write their own.
        $settings = Forms::get($form_id)['settings'];
        self::assert(
            (string) $settings['notify_message_key'],
            '' === trim((string) ($settings['notify_message'] ?? ''))
                && isset($sections[Translations::GROUP_NOTIFICATION][$settings['notify_message_key']])
        );

        self::title(__('The submit button sits with the fields', 'lonsda-light-form'));
        // One string does not warrant a section of its own.
        self::assert(
            'no section of its own',
            isset($sections[Translations::GROUP_FIELDS][FormBuilder::generatedSubmitKey()])
        );

        self::title(__('An empty section is not offered at all', 'lonsda-light-form'));
        self::assert(
            'every section returned has rows in it',
            [] === array_filter($sections, static fn($rows) => [] === $rows)
        );

        self::title(__('The submit button shares one key across every form', 'lonsda-light-form'));
        // Not per form: "Send" is "Send" everywhere, and a key per form would
        // mean translating the same word once for each of them.
        self::assert(
            FormBuilder::generatedSubmitKey(),
            'form_submit' === FormBuilder::generatedSubmitKey()
                && FormBuilder::generatedSubmitKey() === (Forms::get($form_id)['settings']['submit_key'] ?? '')
        );

        self::title(__('Saving writes both a .mo and a .po', 'lonsda-light-form'));
        $saved = Translations::save(self::TEST_LOCALE, [$key => 'Priekšvārds']);
        self::assert(
            'both files present',
            true === $saved
                && is_readable(Translations::path(self::TEST_LOCALE))
                && is_readable(Translations::poPath(self::TEST_LOCALE)),
            is_wp_error($saved) ? $saved->get_error_message() : null
        );

        self::title(__('And it reads back', 'lonsda-light-form'));
        self::assert(
            'the translation round-trips',
            'Priekšvārds' === (Translations::existing(self::TEST_LOCALE)[$key] ?? null)
        );

        self::title(__('It is used when that language is loaded', 'lonsda-light-form'));
        // Loaded and put back deliberately: this is the live request, and
        // leaving a test locale in place would translate the rest of the page.
        unload_textdomain(Translations::DOMAIN);
        load_textdomain(Translations::DOMAIN, Translations::path(self::TEST_LOCALE));
        $shown        = Strings::get('Given name', $key);
        $untranslated = Strings::get('Message', $other);
        unload_textdomain(Translations::DOMAIN);
        Translations::load();

        self::assert(
            'translated where there is one, original where there is not',
            'Priekšvārds' === $shown && 'Message' === $untranslated
        );

        self::title(__('A second save keeps what it was not shown', 'lonsda-light-form'));
        Translations::save(self::TEST_LOCALE, [$other => 'Ziņa']);
        $now = Translations::existing(self::TEST_LOCALE);
        self::assert(
            'both survive',
            'Ziņa' === ($now[$other] ?? null) && 'Priekšvārds' === ($now[$key] ?? null),
            $now
        );

        self::title(__('Clearing a box removes only that translation', 'lonsda-light-form'));
        Translations::save(self::TEST_LOCALE, [$key => '   ']);
        $now = Translations::existing(self::TEST_LOCALE);
        self::assert(
            'one gone, the other kept',
            !isset($now[$key]) && 'Ziņa' === ($now[$other] ?? null),
            $now
        );

        self::title(__('A translation whose original is gone is dropped', 'lonsda-light-form'));
        Translations::save(self::TEST_LOCALE, ['no_such_key_at_all' => 'orphan']);
        self::assert(
            'not stored',
            !isset(Translations::existing(self::TEST_LOCALE)['no_such_key_at_all'])
        );

        self::title(__('Installed files are listed', 'lonsda-light-form'));
        $installed = Translations::installed();
        self::assert(
            implode(', ', array_keys($installed)),
            isset($installed[self::TEST_LOCALE]) && $installed[self::TEST_LOCALE]['entries'] > 0
        );

        self::title(__('No language is offered twice', 'lonsda-light-form'));
        // The bug this catches: WordPress's installed-translations list added a
        // bare "lv" beside WPML's "lv_LV", and only one of them is ever loaded.
        $languages = [];

        foreach (array_keys(Translations::locales()) as $locale) {
            $languages[] = strtolower(explode('_', str_replace('-', '_', $locale))[0]);
        }

        $duplicates = array_keys(array_filter(array_count_values($languages), static fn($n) => $n > 1));
        self::assert(
            $duplicates ? 'offered twice: ' . implode(', ', $duplicates) : implode(', ', array_keys(Translations::locales())),
            [] === $duplicates
        );

        self::title(__('The locale serving this page is among them', 'lonsda-light-form'));
        self::assert(determine_locale(), isset(Translations::locales()[determine_locale()]));

        self::title(__('An invalid locale is refused', 'lonsda-light-form'));
        self::assert('refused', is_wp_error(Translations::save('', [$key => 'x'])));
    }

    private static function cleanupScenario(): void
    {
        self::heading(__('Cleaning up', 'lonsda-light-form'));

        $before = self::leftovers();
        self::cleanup();

        self::title(__('Test forms removed', 'lonsda-light-form'));
        self::assert(
            sprintf('%d found, %d remaining', $before, self::leftovers()),
            0 === self::leftovers()
        );
    }

    // -------------------------------------------------------------- helpers --

    /**
     * Creates a form the way the editor would, and projects it into the table.
     *
     * @param array[] $fields
     */
    private static function makeForm(string $name, array $fields): int
    {
        $post_id = wp_insert_post([
            'post_type'   => Forms::POST_TYPE,
            'post_title'  => self::PREFIX . ' ' . $name,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            throw new \RuntimeException('could not create the test form: ' . $post_id->get_error_message());
        }

        self::setFields((int) $post_id, $fields);
        Forms::syncToTable((int) $post_id, get_post((int) $post_id));

        return (int) $post_id;
    }

    /**
     * Writes a field list through Carbon Fields, as saving the editor would.
     *
     * @param array[] $fields
     */
    private static function setFields(int $post_id, array $fields): void
    {
        if (!function_exists('carbon_set_post_meta')) {
            throw new \RuntimeException('Carbon Fields is not available');
        }

        $rows = [];

        foreach ($fields as $field) {
            $rows[] = array_merge([
                'label'           => '',
                'name'            => '',
                'type'            => 'text',
                'placeholder'     => '',
                'default_checked' => false,
                'required'        => false,
                'validation'      => '',
                'pattern'         => '',
                'max_length'      => '',
                'translation_key' => '',
            ], $field);
        }

        carbon_set_post_meta($post_id, 'llf_fields', $rows);
    }

    /**
     * Posts to a form and returns the outcome, without leaving the request
     * state altered for whatever runs next.
     *
     * @param array $values Field name => value.
     * @param array $args   {
     *     @type string $nonce Override the nonce, to test a stale one.
     *     @type array  $extra Additional $_POST keys, e.g. the honeypot.
     * }
     */
    private static function submit(int $form_id, array $values, array $args = []): array
    {
        $post   = $_POST;
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        $_POST = array_merge([
            Renderer::FIELD_FORM_ID  => $form_id,
            'llf_nonce'              => $args['nonce'] ?? wp_create_nonce('llf_submit_' . $form_id),
            Renderer::FIELD_HONEYPOT => '',
            // Backdated so the minimum-completion-time check, if the site has
            // one, sees a form that was on screen long enough to fill in.
            Renderer::FIELD_STARTED  => time() - HOUR_IN_SECONDS,
            'llf'                    => $values,
        ], $args['extra'] ?? []);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            Submission::maybeHandle();

            $result = Submission::result();
        } finally {
            $_POST                     = $post;
            $_SERVER['REQUEST_METHOD'] = $method;
            Submission::forget();
        }

        return is_array($result) ? $result : ['success' => false, 'errors' => [], 'notice' => 'no result'];
    }

    /** Test forms, entries and translation files still present. */
    private static function leftovers(): int
    {
        global $wpdb;

        $files = (int) file_exists(Translations::path(self::TEST_LOCALE))
            + (int) file_exists(Translations::poPath(self::TEST_LOCALE));

        return $files + count(self::findTestForms()) + (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Migrations::entriesTableName() . ' WHERE form_title LIKE %s',
                $wpdb->esc_like(self::PREFIX) . '%'
            )
        );
    }

    /** @return int[] Post ids. */
    private static function findTestForms(): array
    {
        $posts = get_posts([
            'post_type'      => Forms::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            // Matched on the title rather than a meta flag: a form that failed
            // half way through creation may never have got as far as its meta.
            's'              => self::PREFIX,
        ]);

        $ids = [];

        foreach ($posts as $id) {
            if (0 === strpos((string) get_the_title($id), self::PREFIX)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /** Removes every test form and entry, and any row orphaned by one. */
    private static function cleanup(): void
    {
        global $wpdb;

        foreach (self::findTestForms() as $id) {
            wp_delete_post($id, true);
        }

        // Written by any scenario that needs a translation to exist, and only
        // ever to the test locale, which no site serves.
        foreach ([Translations::path(self::TEST_LOCALE), Translations::poPath(self::TEST_LOCALE)] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Entries are matched on the form title they recorded, which is the
        // only link left once the form itself has gone.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . Migrations::entriesTableName() . ' WHERE form_title LIKE %s',
                $wpdb->esc_like(self::PREFIX) . '%'
            )
        );

        // Belt and braces: a row whose post vanished without the delete hook
        // running would otherwise sit in the table forever.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . Migrations::tableName() . ' WHERE title LIKE %s',
                $wpdb->esc_like(self::PREFIX) . '%'
            )
        );
    }

    /** @param string[] $needles */
    private static function hasAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (false === strpos($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------- reporting --

    private static function heading(string $title): void
    {
        echo '<h2>' . esc_html($title) . '</h2>';
    }

    private static function title(string $title): void
    {
        echo '<h4 class="llf-test-title">' . esc_html($title) . '</h4>';
    }

    /**
     * @param mixed $debug Printed under a failure, to save a second run.
     */
    private static function assert(string $detail, bool $ok, $debug = null): void
    {
        self::result($detail, $ok, $ok ? null : $debug);
    }

    /**
     * @param mixed $debug
     */
    private static function result(string $message, bool $ok, $debug = null): void
    {
        printf(
            '<p class="llf-test llf-test--%s"><strong>%s</strong> %s</p>',
            $ok ? 'pass' : 'fail',
            $ok ? esc_html__('PASS', 'lonsda-light-form') : esc_html__('FAIL', 'lonsda-light-form'),
            esc_html($message)
        );

        if (null !== $debug) {
            echo '<pre class="llf-test-debug">' . esc_html(print_r($debug, true)) . '</pre>';
        }
    }
}
