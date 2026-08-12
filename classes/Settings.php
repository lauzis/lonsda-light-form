<?php

namespace LonsdaLightForm;

/**
 * Lonsda Light Form's settings page.
 *
 * Fields come from config/settings.json plus two schemas shipped by
 * lauzis/wp-plugin-packages, so the logging and AI provider controls match
 * every other plugin here.
 */
class Settings
{
    private const PREFIX = 'llf_';

    /** @return \Lauzis\WpPackages\Settings\Settings|null */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings(LLF_SLUG, [
            'title'       => __('Settings', 'lonsda-light-form'),
            'mode'        => 'tabs',
            'page_parent' => LLF_SLUG,
            'page_file'   => LLF_SLUG . '-settings',
        ]);
    }

    /** Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        $page->callback('llf_admin_email', static fn(): string => (string) get_option('admin_email'));

        // Passed as a callable rather than a string: the panel lists the forms
        // that exist and carries a nonce, both of which have to be current when
        // the page is drawn rather than when the schema is read.
        $page->callback('llf_transfer_ui', [Admin::class, 'transferPanel']);
        $page->callback('llf_recaptcha_test', [Admin::class, 'recaptchaTest']);
        $page->callback('llf_styles_preview', [Admin::class, 'stylesPreview']);
        $page->callback('llf_logs_view', [Admin::class, 'logsPanel']);

        $page->register(LLF_DIR . 'config/settings.json', [
            'prefix' => self::PREFIX,
            'domain' => 'lonsda-light-form',
        ]);

        $page->register(\WpPackages_Registry::schema('logs'), [
            'prefix' => self::PREFIX,
            'domain' => 'wp-plugin-packages',
        ]);

        // Adds the viewer to the section the package just declared, rather than
        // to one of our own: the checkbox that switches logging on and the log
        // it writes belong on the same tab. Whether it appears at all is this
        // plugin's decision, which is why the panel is registered here and not
        // by the package.
        $page->register(LLF_DIR . 'config/logs.json', [
            'prefix' => self::PREFIX,
            'domain' => 'lonsda-light-form',
        ]);

        $page->render();
    }

    /**
     * @param string $id      Bare id as written in the schema.
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $id, $default = null)
    {
        $page = self::page();

        return $page ? $page->get($id, $default) : $default;
    }
}
