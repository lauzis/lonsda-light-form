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

        $status   = isset($_GET['llf_status']) ? sanitize_key(wp_unslash($_GET['llf_status'])) : '';
        $language = isset($_GET['llf_language']) ? sanitize_text_field(wp_unslash($_GET['llf_language'])) : '';
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
                        'llf_done'     => 1,
                        'llf_form'     => $form_id,
                        'llf_status'   => $status,
                        'llf_language' => $language,
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

    /**
     * A sample of what the built-in styles do, on the settings page.
     *
     * Drawn with the same renderer and the same wording a real form uses, so
     * the sample cannot quietly stop matching what a visitor is shown. The
     * alternative — markup copied out of the renderer into here — is a sample
     * that goes stale the first time either changes, which is worse than none.
     *
     * Shown whether or not the styles are switched on: knowing what ticking the
     * box would do is the reason to look at this at all.
     */
    public static function stylesPreview(): string
    {
        // Not a <form>: this sits inside the settings page's own form, and one
        // form inside another is neither valid nor recoverable. Only the class
        // matters — the stylesheet targets that rather than the element.
        $field = \LonsdaLightForm\Renderer::field(
            [
                'label'      => __('Email', 'lonsda-light-form'),
                'name'       => 'preview',
                'type'       => 'text',
                'required'   => true,
                'validation' => 'email',
            ],
            'not-an-email',
            \LonsdaLightForm\Strings::general('error_email'),
            // Disabled for the same reason: a live input here would be posted
            // along with the settings.
            ['disabled' => 'disabled']
        );

        ob_start();
        ?>
        <div class="llf-styles-preview">
            <h3><?php esc_html_e('What they look like', 'lonsda-light-form'); ?></h3>

            <p class="description">
                <?php
                echo \LonsdaLightForm\Styles::enabled()
                    ? esc_html__('These three are in use on the front end. Everything else about the form is the theme\'s.', 'lonsda-light-form')
                    : esc_html__('These are switched off, so a visitor sees none of it. This is what ticking the box above would give you.', 'lonsda-light-form');
                ?>
            </p>

            <div class="llf-styles-sample">
                <div class="llf-form">
                    <div class="llf-notice llf-notice--success">
                        <?php echo wp_kses_post(\LonsdaLightForm\FormBuilder::defaultSuccessMessage()); ?>
                    </div>

                    <div class="llf-notice llf-notice--error">
                        <?php echo esc_html(\LonsdaLightForm\Strings::general('notice_errors')); ?>
                    </div>

                    <?php echo $field; // Escaped field by field by the renderer. ?>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e('Each part is a class of its own — llf-notice--success, llf-notice--error, llf-input--error and llf-error — and the colours are custom properties on .llf-form, so a theme can recolour all of it with one rule instead of turning the lot off.', 'lonsda-light-form'); ?>
            </p>

            <style>
                /* The sample's own frame. What is inside it is the real
                   stylesheet, enqueued for this page like any other. */
                .llf-styles-sample {
                    max-width: 480px;
                    margin: 12px 0;
                    padding: 16px;
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                    background: #fff;
                }
                .llf-styles-sample .llf-field { margin-bottom: 0; }
                .llf-styles-sample .llf-input { width: 100%; }
            </style>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * The log, on the Logging tab.
     *
     * The listing itself is the shared package's — every plugin here writes the
     * same log and would otherwise grow its own reader for it. What belongs to
     * this plugin is whether to show it at all, and the action behind the clear
     * button, which has to check a capability this plugin decides on.
     */
    public static function logsPanel(): string
    {
        $logger = \LonsdaLightForm\Logs::logger();

        if (!$logger || !class_exists('\Lauzis\WpPackages\Logs\Viewer')) {
            // An older copy of the shared package won the version race — see
            // WpPackages_Registry. Everything else on this page still works, so
            // this says what is missing rather than fataling.
            return '<p class="description">'
                . esc_html__('The log reader needs a newer copy of the shared package than the one running.', 'lonsda-light-form')
                . '</p>';
        }

        $viewer = new \Lauzis\WpPackages\Logs\Viewer($logger, ['clear' => 'llf_clear_logs']);

        return $viewer->render();
    }

    /** Empties the log, from the button on that panel. */
    public static function handleClearLogs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do that.', 'lonsda-light-form'));
        }

        check_admin_referer('llf_clear_logs');

        \LonsdaLightForm\Logs::clear();

        wp_safe_redirect(admin_url('admin.php?page=' . LLF_SLUG . '-settings'));
        exit;
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

        // The front-end stylesheet, for the sample on the Appearance tab. It is
        // the real file rather than a copy of it, which is the only way the
        // sample stays true.
        \LonsdaLightForm\Styles::enqueuePreview();

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

    /**
     * The Testing tab on the form editor.
     *
     * Markup only. It sits inside the editor's own form, which cannot contain
     * another, so the buttons post on their own from an enqueued script.
     */
    public static function testPanel(): string
    {
        $post_id = (int) get_the_ID();
        $form    = \LonsdaLightForm\Forms::get(\LonsdaLightForm\Forms::tableIdForPost($post_id));
        $s       = $form['settings'] ?? [];
        $user    = wp_get_current_user();

        ob_start();
        ?>
        <div class="llf-test-mail" data-post="<?php echo esc_attr((string) $post_id); ?>">
            <?php if (!$form) : ?>
                <p class="description"><?php esc_html_e('Publish the form once and this tab can send it to you.', 'lonsda-light-form'); ?></p>
            <?php else : ?>
                <p class="description" style="max-width:760px;">
                    <?php esc_html_e('Sends the real message — the same templates, placeholders and translations a submission produces — with made-up answers, to the address below. Nothing is stored as an entry, and the subject is marked so it cannot be mistaken for a real enquiry.', 'lonsda-light-form'); ?>
                </p>

                <p>
                    <label for="llf-test-to"><strong><?php esc_html_e('Send to', 'lonsda-light-form'); ?></strong></label><br>
                    <input type="email" id="llf-test-to" class="regular-text"
                           value="<?php echo esc_attr($user ? $user->user_email : ''); ?>">
                </p>

                <p>
                    <label for="llf-test-locale"><strong><?php esc_html_e('Language', 'lonsda-light-form'); ?></strong></label><br>
                    <select id="llf-test-locale">
                        <option value=""><?php
                            printf(
                                /* translators: %s: locale this screen is being served as */
                                esc_html__('Site default (%s)', 'lonsda-light-form'),
                                esc_html(determine_locale())
                            );
                        ?></option>
                        <?php foreach (\LonsdaLightForm\Translations::locales() as $llf_code => $llf_label) : ?>
                            <option value="<?php echo esc_attr($llf_code); ?>"><?php echo esc_html($llf_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description">
                        <?php esc_html_e('Sends it as a visitor in that language would receive it.', 'lonsda-light-form'); ?>
                    </span>
                </p>

                <p>
                    <button type="button" class="button" id="llf-test-notification"
                        <?php disabled('' === trim((string) ($s['notify_to'] ?? ''))); ?>>
                        <?php esc_html_e('Send test notification', 'lonsda-light-form'); ?>
                    </button>

                    <button type="button" class="button" id="llf-test-auto-reply"
                        <?php disabled(empty($s['auto_reply'])); ?>>
                        <?php esc_html_e('Send test auto reply', 'lonsda-light-form'); ?>
                    </button>
                </p>

                <?php
                // A disabled button with no explanation is a puzzle, so each one
                // says what would switch it on.
                if ('' === trim((string) ($s['notify_to'] ?? ''))) :
                    ?>
                    <p class="description"><?php esc_html_e('The notification has no recipient yet — set one on the Notifications tab.', 'lonsda-light-form'); ?></p>
                <?php endif; ?>

                <?php if (empty($s['auto_reply'])) : ?>
                    <p class="description"><?php esc_html_e('The auto reply is switched off — turn it on on the Auto reply tab.', 'lonsda-light-form'); ?></p>
                <?php endif; ?>

                <p class="description">
                    <?php esc_html_e('Both read what was last saved, so save the form before testing a change.', 'lonsda-light-form'); ?>
                </p>

                <p id="llf-test-status" aria-live="polite"></p>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /** Sends a test message and answers as JSON. */
    public static function handleTestMail(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(['sent' => false, 'message' => __('You are not allowed to do that.', 'lonsda-light-form')], 403);
        }

        check_admin_referer('llf-test-mail');

        $result = \LonsdaLightForm\TestMail::send(
            isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0,
            isset($_POST['which']) ? sanitize_key(wp_unslash($_POST['which'])) : '',
            isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '',
            isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : ''
        );

        wp_send_json($result);
    }

    /**
     * A reference panel of placeholders, beside the form editor.
     *
     * The fixed ones could be written into the help text, but the useful half
     * cannot: which field tokens exist depends on the form being edited, and
     * that is exactly what somebody writing a notification needs to hand.
     */
    public static function registerPlaceholderBox(): void
    {
        add_meta_box(
            'llf-placeholders',
            __('Placeholders', 'lonsda-light-form'),
            [self::class, 'renderPlaceholderBox'],
            \LonsdaLightForm\Forms::POST_TYPE,
            'side',
            // Below Publish, which sits at 'core'.
            'default'
        );
    }

    /** @param \WP_Post $post */
    public static function renderPlaceholderBox($post): void
    {
        $form = \LonsdaLightForm\Forms::get(\LonsdaLightForm\Forms::tableIdForPost((int) $post->ID));

        echo self::placeholderPanel(
            $form['settings']['fields'] ?? [],
            __('For the notification and auto reply. Click one to copy it.', 'lonsda-light-form'),
            __('Save the form and its fields appear here, one token each.', 'lonsda-light-form')
        );
    }

    /**
     * The list of placeholder tokens, for anywhere somebody types one.
     *
     * Shared by the form editor, where the wording is written, and the
     * translations screen, where it is written again in another language and
     * the tokens have to survive the trip. A translator who retypes {site_name}
     * as {site-name} gets a subject with a brace in it, and nothing anywhere
     * says why — so the tokens are put in front of them, copyable, rather than
     * left to memory.
     *
     * @param array  $fields Field definitions whose names are tokens too.
     * @param string $intro  Line above the list.
     * @param string $empty  What to say when there are no fields to list.
     */
    public static function placeholderPanel(array $fields, string $intro, string $empty): string
    {
        ob_start();
        ?>
        <div class="llf-placeholders">
            <p class="description"><?php echo esc_html($intro); ?></p>

            <h4><?php esc_html_e('Field answers', 'lonsda-light-form'); ?></h4>

            <?php if (!$fields) : ?>
                <p class="description"><?php echo esc_html($empty); ?></p>
            <?php else : ?>
                <ul class="llf-placeholder-list">
                    <?php foreach ($fields as $field) : ?>
                        <li>
                            <code class="llf-copy" tabindex="0" role="button"><?php echo esc_html('{' . $field['name'] . '}'); ?></code>
                            <span class="description"><?php echo esc_html((string) ($field['label'] ?? '')); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4><?php esc_html_e('Always available', 'lonsda-light-form'); ?></h4>
            <ul class="llf-placeholder-list">
                <?php foreach (\LonsdaLightForm\Notifications::placeholderReference() as $token => $what) : ?>
                    <li>
                        <code class="llf-copy" tabindex="0" role="button"><?php echo esc_html($token); ?></code>
                        <span class="description"><?php echo esc_html($what); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="description">
                <?php esc_html_e('Underscores, not dashes: {site_name} is replaced and {site-name} is not — an unknown token is left in the message as it was typed. A field named the same as a fixed one does not displace it, since the fixed set means the same on every form.', 'lonsda-light-form'); ?>
            </p>
        </div>

        <style>
            .llf-placeholder-list { margin: 4px 0 14px; }
            .llf-placeholder-list li { margin: 0 0 6px; line-height: 1.4; }
            .llf-placeholder-list code { cursor: pointer; display: inline-block; }
            .llf-placeholder-list code:hover { background: #2271b1; color: #fff; }
            .llf-placeholder-list .description { display: block; font-size: 11px; }
            .llf-copied { background: #00a32a !important; color: #fff !important; }
        </style>
        <?php

        return (string) ob_get_clean();
    }

    /** Loads the form editor's own script, for the Testing tab. */
    /** The copy-a-token script, for either screen that shows the panel. */
    public static function enqueuePlaceholders(): void
    {
        wp_enqueue_script('llf-placeholders', LLF_URL . 'assets/js/placeholders.js', [], LLF_VERSION, true);
    }

    /** The same panel appears on the translations screen. */
    public static function enqueueTranslations(): void
    {
        if (!isset($_GET['page']) || LLF_SLUG . '-translations' !== $_GET['page']) {
            return;
        }

        self::enqueuePlaceholders();
    }

    public static function enqueueFormEditor(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || \LonsdaLightForm\Forms::POST_TYPE !== $screen->post_type || 'post' !== $screen->base) {
            return;
        }

        wp_enqueue_script('llf-form-editor', LLF_URL . 'assets/js/form-editor.js', [], LLF_VERSION, true);
        self::enqueuePlaceholders();

        wp_localize_script('llf-form-editor', 'LLFFormEditor', [
            'postUrl' => admin_url('admin-post.php'),
            'nonce'   => wp_create_nonce('llf-test-mail'),
            'i18n'    => [
                'sending' => __('Sending…', 'lonsda-light-form'),
                'failed'  => __('The test could not be completed.', 'lonsda-light-form'),
                'noEmail' => __('Enter an address to send to.', 'lonsda-light-form'),
            ],
        ]);
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
