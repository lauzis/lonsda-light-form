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

    /** Renders the help page. */
    public static function renderHelp(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LLF_DIR . 'templates/help.php';
    }
}
