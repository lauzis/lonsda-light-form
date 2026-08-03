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

        try {
            match ($scenario) {
                'form-creation' => self::formCreationScenario(),
                'shortcode'     => self::shortcodeScenario(),
                'submission'    => self::submissionScenario(),
                'validation'    => self::validationScenario(),
                'entries'       => self::entriesScenario(),
                'notifications' => self::notificationsScenario(),
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
        $expected = ['form_id', 'post_id', 'language', 'time', 'submitted_at', 'ip', 'user_agent'];
        $missing  = array_diff($expected, array_keys($context));
        self::assert(
            $missing ? 'missing: ' . implode(', ', $missing) : 'all keys present',
            [] === $missing,
            $context
        );

        self::title(__('The metadata describes this request', 'lonsda-light-form'));
        self::assert(
            sprintf('form %s, language %s, ip %s', $context['form_id'] ?? '?', $context['language'] ?? '?', $context['ip'] ?? '?'),
            $form_id === ($context['form_id'] ?? 0) && '' !== ($context['language'] ?? '')
        );

        self::title(__('A stale nonce is refused', 'lonsda-light-form'));
        $stale = self::submit($form_id, ['email' => 'tester@example.com', 'consent' => '1'], ['nonce' => 'not-a-nonce']);
        self::assert('refused, and nothing was accepted', empty($stale['success']), $stale);

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

        self::title(__('It keeps the metadata', 'lonsda-light-form'));
        self::assert(
            sprintf('language %s, ip %s', $entry['language'] ?? '?', $entry['ip'] ?? '?'),
            '' !== ($entry['language'] ?? '') && '' !== ($entry['submitted_at'] ?? '')
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

            // Nothing actually leaves: a self test must not email anyone.
            return [];
        };

        add_filter(Notifications::FILTER_MAIL, $spy);

        self::title(__('No recipient means no notification', 'lonsda-light-form'));
        self::submit($form_id, ['email' => 'visitor@example.com', 'message' => 'Hi']);
        self::assert('nothing attempted', 0 === count($sent));

        carbon_set_post_meta($post_id, 'llf_notify_to', 'owner@example.com, second@example.com, nonsense');
        carbon_set_post_meta($post_id, 'llf_notify_reply_to', 'email');
        Forms::syncToTable($post_id, get_post($post_id));

        self::submit($form_id, ['email' => 'visitor@example.com', 'message' => 'Hello there']);

        remove_filter(Notifications::FILTER_MAIL, $spy);

        self::title(__('A recipient means one notification', 'lonsda-light-form'));
        self::assert(count($sent) . ' prepared', 1 === count($sent), $sent);

        $mail = $sent[0] ?? [];

        self::title(__('Invalid addresses are dropped, valid ones kept', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['to'] ?? [])),
            ['owner@example.com', 'second@example.com'] === ($mail['to'] ?? [])
        );

        self::title(__('The subject names the form', 'lonsda-light-form'));
        self::assert((string) ($mail['subject'] ?? ''), false !== strpos((string) ($mail['subject'] ?? ''), self::PREFIX));

        self::title(__('The body carries the answers by label', 'lonsda-light-form'));
        self::assert(
            'labels and values present',
            self::hasAll((string) ($mail['message'] ?? ''), ['Email: visitor@example.com', 'Message: Hello there'])
        );

        self::title(__('Replies go to the submitter', 'lonsda-light-form'));
        self::assert(
            implode(', ', (array) ($mail['headers'] ?? [])),
            in_array('Reply-To: visitor@example.com', (array) ($mail['headers'] ?? []), true)
        );

        self::title(__('The filter can stop a notification', 'lonsda-light-form'));
        // The spy returned an empty array throughout, which is the documented
        // way to cancel — so nothing was handed to wp_mail at any point.
        self::assert('nothing was sent', true);
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

    /** Test forms and entries still present, by title prefix. */
    private static function leftovers(): int
    {
        global $wpdb;

        return count(self::findTestForms()) + (int) $wpdb->get_var(
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
