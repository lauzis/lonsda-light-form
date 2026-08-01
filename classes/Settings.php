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

        $page->register(LLF_DIR . 'config/settings.json', [
            'prefix' => self::PREFIX,
            'domain' => 'lonsda-light-form',
        ]);

        $page->register(\WpPackages_Registry::schema('logs'), [
            'prefix' => self::PREFIX,
            'domain' => 'wp-plugin-packages',
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
