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
     */
    private static function redirectWithNotice($result): void
    {
        $url = add_query_arg(
            is_wp_error($result)
                ? ['llf_error' => rawurlencode($result->get_error_message())]
                : ['llf_done' => 1],
            admin_url('admin.php?page=' . LLF_SLUG . '-translations')
        );

        wp_safe_redirect($url);
        exit;
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
