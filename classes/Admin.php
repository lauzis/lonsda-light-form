<?php

namespace LonsdaLightForm;

/**
 * Admin screen and setup notice.
 */
class Admin
{
    /** Hooked on init, once the settings schema exists. */
    public static function init(): void
    {
        if (!is_admin() || !class_exists('WpPackages_Registry')) {
            return;
        }

        $notices = \WpPackages_Registry::notices(LLF_SLUG, [
            'store'      => 'user',
            'version'    => LLF_VERSION,
            'capability' => 'manage_options',
        ])->boot();

        // Toasts are used for submission-list actions on the admin screen.
        \WpPackages_Registry::toasts(LLF_SLUG);

        add_action('admin_notices', static function () use ($notices): void {
            foreach (self::setupIssues() as $index => $issue) {
                $notices->add(
                    new \Lauzis\WpPackages\Notices\Notice(
                        'setup-' . $index,
                        $issue,
                        'warning',
                        \Lauzis\WpPackages\Notices\Notice::VERSION
                    )
                );
            }
        }, 5);
    }

    /**
     * Configuration that has to be in place before submissions go anywhere.
     *
     * @return string[]
     */
    private static function setupIssues(): array
    {
        $issues    = [];
        $recipient = trim((string) Settings::get('recipient', ''));

        if ('' === $recipient) {
            $issues[] = __('No recipient email is configured, so submissions would not be delivered anywhere.', 'lonsda-light-form');
        } elseif (!is_email($recipient)) {
            $issues[] = sprintf(
                /* translators: %s: the configured value */
                __('The recipient email does not look valid: %s', 'lonsda-light-form'),
                $recipient
            );
        }

        if (!Settings::get('store_submissions', true) && '' === $recipient) {
            $issues[] = __('Submissions are neither stored nor emailed, so they would be discarded.', 'lonsda-light-form');
        }

        return $issues;
    }

    /** Renders the list of forms. */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $forms = Forms::all();

        require LLF_DIR . 'templates/forms.php';
    }

    /**
     * Handles the POT download and the translation upload.
     *
     * On admin_init rather than in the page template: a download has to send
     * headers before any output, and a redirect after an upload has to happen
     * before the admin page starts rendering.
     */
    public static function handleTranslationActions(): void
    {
        if (!isset($_GET['page'], $_REQUEST['llf_action']) || LLF_SLUG . '-translations' !== $_GET['page']) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage translations.', 'lonsda-light-form'));
        }

        $action = sanitize_key(wp_unslash($_REQUEST['llf_action']));

        if ('download' === $action) {
            check_admin_referer('llf-translations');

            $body = \LonsdaLightForm\Translations::pot();

            nocache_headers();
            header('Content-Type: text/x-gettext-translation; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . \LonsdaLightForm\Translations::DOMAIN . '.pot"');
            header('Content-Length: ' . strlen($body));

            echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- a POT file, not markup.
            exit;
        }

        if ('save' === $action) {
            check_admin_referer('llf-translations');

            $pairs = [];

            if (isset($_POST['llf_tr']) && is_array($_POST['llf_tr'])) {
                foreach (wp_unslash($_POST['llf_tr']) as $key => $value) {
                    // Not sanitize_text_field: a translation is prose and may
                    // legitimately contain characters that would strip.
                    $pairs[sanitize_key($key)] = wp_check_invalid_utf8((string) $value);
                }
            }

            $result = \LonsdaLightForm\Translations::save(
                isset($_POST['llf_locale']) ? sanitize_text_field(wp_unslash($_POST['llf_locale'])) : '',
                $pairs
            );

            self::redirectWithNotice($result, [
                'llf_locale' => isset($_POST['llf_locale']) ? sanitize_text_field(wp_unslash($_POST['llf_locale'])) : '',
                'llf_form'   => isset($_POST['llf_form']) ? (int) $_POST['llf_form'] : 0,
            ]);
        }

        if ('upload' === $action) {
            check_admin_referer('llf-translations');

            $result = \LonsdaLightForm\Translations::store(
                $_FILES['llf_file'] ?? [],
                isset($_POST['llf_locale']) ? sanitize_text_field(wp_unslash($_POST['llf_locale'])) : ''
            );

            self::redirectWithNotice($result);
        }

        if ('delete' === $action) {
            check_admin_referer('llf-translations');

            $locale  = isset($_GET['llf_locale']) ? sanitize_text_field(wp_unslash($_GET['llf_locale'])) : '';
            $deleted = \LonsdaLightForm\Translations::delete($locale);

            self::redirectWithNotice(
                $deleted ? true : new \WP_Error('llf_not_deleted', __('That file could not be removed.', 'lonsda-light-form'))
            );
        }
    }

    /**
     * @param true|\WP_Error $result
     * @param array          $keep Query args to carry back, so the editor
     *                             reopens where it was left.
     */
    private static function redirectWithNotice($result, array $keep = []): void
    {
        $url = add_query_arg(
            array_merge(
                $keep,
                is_wp_error($result)
                    ? ['llf_error' => rawurlencode($result->get_error_message())]
                    : ['llf_done' => 1]
            ),
            admin_url('admin.php?page=' . LLF_SLUG . '-translations')
        );

        wp_safe_redirect($url);
        exit;
    }

    /** Handles the entries CSV download and entry deletion. */
    public static function handleEntryActions(): void
    {
        if (!isset($_GET['page'], $_GET['llf_action']) || LLF_SLUG . '-entries' !== $_GET['page']) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage entries.', 'lonsda-light-form'));
        }

        check_admin_referer('llf-entries');

        $action  = sanitize_key(wp_unslash($_GET['llf_action']));
        $form_id = isset($_GET['llf_form']) ? (int) $_GET['llf_form'] : 0;

        if ('csv' === $action) {
            $body = \LonsdaLightForm\Entries::csv($form_id);

            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="lonsda-entries-' . gmdate('Y-m-d') . '.csv"');
            header('Content-Length: ' . strlen($body));

            echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV, not markup.
            exit;
        }

        $status = isset($_GET['llf_status']) ? sanitize_key(wp_unslash($_GET['llf_status'])) : '';
        $entry  = isset($_GET['llf_entry']) ? (int) $_GET['llf_entry'] : 0;

        if ('delete' === $action || 'unread' === $action) {
            if ('delete' === $action) {
                \LonsdaLightForm\Entries::delete($entry);
            } else {
                \LonsdaLightForm\Entries::setStatus($entry, \LonsdaLightForm\Entries::STATUS_NEW);
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'llf_done'   => 1,
                        'llf_form'   => $form_id,
                        'llf_status' => $status,
                    ],
                    admin_url('admin.php?page=' . LLF_SLUG . '-entries')
                )
            );
            exit;
        }
    }

    /**
     * The Import / Export panel on the settings page.
     *
     * Returned as markup rather than echoed, because Carbon Fields renders an
     * html field wherever it likes. It sits inside the settings form, so it
     * cannot contain a form of its own — HTML forbids nesting them and the
     * browser would silently merge the inputs into the settings form. Export is
     * therefore an ordinary link, and import posts on its own.
     */
    public static function transferPanel(): string
    {
        $forms  = \LonsdaLightForm\Forms::all();
        $base   = admin_url('admin-post.php');
        $export = wp_nonce_url(add_query_arg('action', 'llf_export', $base), 'llf-transfer');

        ob_start();
        ?>
        <div class="llf-transfer">
            <h3><?php esc_html_e('Export', 'lonsda-light-form'); ?></h3>

            <?php if (!$forms) : ?>
                <p><em><?php esc_html_e('There are no forms to export yet.', 'lonsda-light-form'); ?></em></p>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Downloads the chosen forms as JSON: their fields, wording and settings. Entries are not included — they are a record of what people sent, not part of the form.', 'lonsda-light-form'); ?>
                </p>

                <p>
                    <label>
                        <input type="checkbox" id="llf-export-all" checked>
                        <strong><?php esc_html_e('All forms', 'lonsda-light-form'); ?></strong>
                    </label>
                </p>

                <ul class="llf-export-list" style="margin:0 0 12px 22px;">
                    <?php foreach ($forms as $form) : ?>
                        <li>
                            <label>
                                <input type="checkbox" class="llf-export-one"
                                       value="<?php echo esc_attr((string) $form->id); ?>" checked>
                                <?php echo esc_html($form->title ?: __('(no title)', 'lonsda-light-form')); ?>
                                <span class="description">#<?php echo esc_html((string) $form->id); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p>
                    <a class="button button-primary" id="llf-export-go"
                       href="<?php echo esc_url($export); ?>">
                        <?php esc_html_e('Download JSON', 'lonsda-light-form'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <hr>

            <h3><?php esc_html_e('Import', 'lonsda-light-form'); ?></h3>
            <p class="description">
                <?php esc_html_e('Choose a file exported from this or another site. Imported forms are always added, never merged into an existing one — two forms can share a title, and replacing the wrong one is worse than leaving a duplicate to delete.', 'lonsda-light-form'); ?>
            </p>

            <p>
                <input type="file" id="llf-import-file" accept="application/json,.json">
                <button type="button" class="button" id="llf-import-go" disabled>
                    <?php esc_html_e('Import', 'lonsda-light-form'); ?>
                </button>
            </p>
            <p id="llf-import-status" class="description" aria-live="polite"></p>

        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * The reCAPTCHA test on the settings page.
     *
     * Shows the real tick box and then verifies the token through the same code
     * a submission uses. Rendering it proves the site key; verifying the token
     * proves the secret — and only doing both distinguishes "the box appears"
     * from "the box actually protects anything".
     */
    public static function recaptchaTest(): string
    {
        $site = trim((string) \LonsdaLightForm\Settings::get('recaptcha_site_key', ''));
        $has  = \LonsdaLightForm\FormBuilder::recaptchaConfigured();

        ob_start();
        ?>
        <div class="llf-recaptcha-test">
            <h3><?php esc_html_e('Test the keys', 'lonsda-light-form'); ?></h3>

            <?php if (!$has) : ?>
                <p class="description">
                    <?php esc_html_e('Fill both keys in and save, then a working tick box appears here to try.', 'lonsda-light-form'); ?>
                </p>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('This is the real challenge, using the keys above. Tick it and press Test: the box appearing means the Site Key is accepted, and the check that follows means the Secret Key is too. Saving the settings first is necessary — the test reads what is stored, not what is typed.', 'lonsda-light-form'); ?>
                </p>

                <div id="llf-recaptcha-widget" style="margin:10px 0;"></div>

                <p>
                    <button type="button" class="button" id="llf-recaptcha-test">
                        <?php esc_html_e('Test', 'lonsda-light-form'); ?>
                    </button>
                </p>

                <p id="llf-recaptcha-status" class="llf-recaptcha-status" aria-live="polite"></p>

                <style>
                    .llf-recaptcha-status { font-weight: 600; }
                    .llf-recaptcha-status.llf-ok { color: #00a32a; }
                    .llf-recaptcha-status.llf-bad { color: #d63638; }
                </style>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /** Verifies a token from the settings-page test. */
    public static function handleRecaptchaTest(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(['success' => false, 'message' => __('You are not allowed to do that.', 'lonsda-light-form')], 403);
        }

        check_admin_referer('llf-transfer');

        $token  = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $result = \LonsdaLightForm\Submission::verifyRecaptcha($token);

        if ($result['success']) {
            wp_send_json([
                'success' => true,
                'message' => __('Both keys work. Google accepted the challenge.', 'lonsda-light-form'),
            ]);
        }

        if ($result['unreachable']) {
            wp_send_json([
                'success' => false,
                'message' => __('Google could not be reached, so the keys could not be checked. Forms accept submissions during an outage rather than turning everyone away.', 'lonsda-light-form'),
            ]);
        }

        $codes   = $result['errors'];
        $message = __('Google rejected the challenge.', 'lonsda-light-form');

        if (in_array('invalid-input-secret', $codes, true)) {
            $message = __('The Secret Key is not valid. Check it against the reCAPTCHA console — it is easy to paste the Site Key into both boxes.', 'lonsda-light-form');
        } elseif (in_array('missing-input-secret', $codes, true)) {
            $message = __('No Secret Key is saved.', 'lonsda-light-form');
        } elseif (in_array('timeout-or-duplicate', $codes, true)) {
            $message = __('That challenge had already been used or had expired. Tick the box again.', 'lonsda-light-form');
        }

        if ($codes) {
            $message .= ' (' . implode(', ', $codes) . ')';
        }

        wp_send_json(['success' => false, 'message' => $message]);
    }

    /**
     * Loads the settings page's own script.
     *
     * Needed because Carbon Fields renders html fields through React, which
     * inserts markup without running any script inside it — so the panels this
     * drives cannot carry their own.
     */
    public static function enqueueSettings(string $hook): void
    {
        if (!isset($_GET['page']) || LLF_SLUG . '-settings' !== $_GET['page']) {
            return;
        }

        $site = trim((string) \LonsdaLightForm\Settings::get('recaptcha_site_key', ''));

        wp_enqueue_script(
            'llf-settings',
            LLF_URL . 'assets/js/settings.js',
            [],
            LLF_VERSION,
            true
        );

        wp_localize_script('llf-settings', 'LLFSettings', [
            'postUrl'   => admin_url('admin-post.php'),
            'exportUrl' => wp_nonce_url(add_query_arg('action', 'llf_export', admin_url('admin-post.php')), 'llf-transfer'),
            'nonce'     => wp_create_nonce('llf-transfer'),
            'siteKey'   => \LonsdaLightForm\FormBuilder::recaptchaConfigured() ? $site : '',
            'i18n'      => [
                'importing'    => __('Importing…', 'lonsda-light-form'),
                'importFailed' => __('The import could not be completed.', 'lonsda-light-form'),
                'checking'     => __('Checking with Google…', 'lonsda-light-form'),
                'tickFirst'    => __('Tick the box first.', 'lonsda-light-form'),
                'badSiteKey'   => __('Google refused to show the challenge, which usually means the Site Key is wrong or this domain is not registered against it.', 'lonsda-light-form'),
                'scriptFailed' => __('Google\'s script did not load, so the challenge cannot be shown.', 'lonsda-light-form'),
                'testFailed'   => __('The test could not be completed.', 'lonsda-light-form'),
            ],
        ]);

        if (\LonsdaLightForm\FormBuilder::recaptchaConfigured()) {
            // Explicit rendering, because the tick box is mounted by React
            // after Google's script has finished scanning the page.
            wp_enqueue_script(
                'llf-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=explicit&onload=llfRecaptchaReady',
                ['llf-settings'],
                null,
                true
            );
        }
    }

    /** Sends the export file. */
    public static function handleExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to export forms.', 'lonsda-light-form'));
        }

        check_admin_referer('llf-transfer');

        $ids = [];

        if (!empty($_GET['ids'])) {
            foreach (explode(',', sanitize_text_field(wp_unslash($_GET['ids']))) as $id) {
                $id = (int) trim($id);

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $body = \LonsdaLightForm\Transfer::export($ids);

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . \LonsdaLightForm\Transfer::filename() . '"');
        header('Content-Length: ' . strlen($body));

        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON, not markup.
        exit;
    }

    /** Receives an uploaded export and answers as JSON. */
    public static function handleImport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(['created' => 0, 'message' => __('You are not allowed to import forms.', 'lonsda-light-form')], 403);
        }

        check_admin_referer('llf-transfer');

        $file = $_FILES['llf_file'] ?? [];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json(['created' => 0, 'message' => __('No file was uploaded.', 'lonsda-light-form')]);
        }

        if (($file['size'] ?? 0) > \LonsdaLightForm\Transfer::MAX_UPLOAD) {
            wp_send_json(['created' => 0, 'message' => __('That file is too large to be an export.', 'lonsda-light-form')]);
        }

        $report  = \LonsdaLightForm\Transfer::import((string) file_get_contents($file['tmp_name']));
        $created = count($report['created']);

        $message = $created
            ? sprintf(
                /* translators: %d: number of forms */
                _n('%d form imported.', '%d forms imported.', $created, 'lonsda-light-form'),
                $created
            )
            : __('Nothing was imported.', 'lonsda-light-form');

        if ($report['errors']) {
            $message .= ' ' . implode(' ', $report['errors']);
        }

        wp_send_json(['created' => $created, 'message' => $message]);
    }

    /** Renders the entries page. */
    public static function renderEntries(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LLF_DIR . 'templates/entries.php';
    }

    /** Renders the translations page. */
    public static function renderTranslations(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LLF_DIR . 'templates/translations.php';
    }

    /** Renders the self tests page. */
    public static function renderTests(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LLF_DIR . 'templates/tests.php';
    }

    /** Renders the help page. */
    public static function renderHelp(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LLF_DIR . 'templates/help.php';
    }
}
