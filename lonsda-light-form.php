<?php
/**
 * Plugin Name: Lonsda Light Form
 * Plugin URI:  https://github.com/lauzis/lonsda-light-form
 * Description: Lightweight Carbon Fields form builder.
 * Version:     0.10.2
 * Author:      Aivars Lauzis
 * Text Domain: lonsda-light-form
 * Domain Path: /languages
 * License:     MIT
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LLF_VERSION', '0.10.2');
define('LLF_DIR', plugin_dir_path(__FILE__));
define('LLF_URL', plugin_dir_url(__FILE__));
define('LLF_SLUG', 'lonsda-light-form');

$llf_autoload = LLF_DIR . 'vendor/autoload.php';

if (!file_exists($llf_autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Lonsda Light Form:</strong> run <code>composer install</code> in the plugin directory.</p></div>';
    });

    return;
}

require_once $llf_autoload;
// Required explicitly: Composer's files autoload runs only one copy of this
// package per request, so the version gate would never see the others.
require_once LLF_DIR . 'vendor/lauzis/wp-plugin-packages/bootstrap.php';

if (!defined('LLF_LOG_PATH')) {
    // Under uploads/, never inside the plugin directory: WordPress deletes and
    // re-extracts that folder on every update.
    $llf_uploads = wp_upload_dir();
    define('LLF_LOG_PATH', str_replace('\\', '/', $llf_uploads['basedir']) . '/lonsda-light-form-logs/');
    unset($llf_uploads);
}

add_action('after_setup_theme', static function (): void {
    if (class_exists('\Carbon_Fields\Carbon_Fields')) {
        \Carbon_Fields\Carbon_Fields::boot();
    }
});

// Carbon Fields fires this on init at priority 0, so it must be attached before
// init runs rather than from inside an init callback.
add_action('carbon_fields_register_fields', ['\LonsdaLightForm\Settings', 'register']);

add_action('admin_menu', static function (): void {
    add_menu_page(
        __('Lonsda Forms', 'lonsda-light-form'),
        __('Lonsda Forms', 'lonsda-light-form'),
        'manage_options',
        LLF_SLUG,
        ['\LonsdaLightForm\Admin', 'render'],
        'dashicons-feedback',
        82
    );

    add_submenu_page(
        LLF_SLUG,
        __('Forms', 'lonsda-light-form'),
        __('Forms', 'lonsda-light-form'),
        'manage_options',
        LLF_SLUG,
        ['\LonsdaLightForm\Admin', 'render']
    );

    // Points at the post type's own add screen, so Carbon Fields renders the
    // structure editor and WordPress handles saving, nonces and capabilities.
    add_submenu_page(
        LLF_SLUG,
        __('Add Form', 'lonsda-light-form'),
        __('Add Form', 'lonsda-light-form'),
        'manage_options',
        'post-new.php?post_type=' . \LonsdaLightForm\Forms::POST_TYPE
    );

    // The unread count in the menu, the way WordPress shows pending anything
    // else. Without it a stored entry is only found by going to look.
    $llf_unread = \LonsdaLightForm\Entries::countNew();
    $llf_label  = __('Entries', 'lonsda-light-form');

    add_submenu_page(
        LLF_SLUG,
        $llf_label,
        $llf_unread
            ? $llf_label . ' <span class="update-plugins count-' . $llf_unread . '"><span class="update-count">'
                . number_format_i18n($llf_unread) . '</span></span>'
            : $llf_label,
        'manage_options',
        LLF_SLUG . '-entries',
        ['\LonsdaLightForm\Admin', 'renderEntries']
    );

    add_submenu_page(
        LLF_SLUG,
        __('Translations', 'lonsda-light-form'),
        __('Translations', 'lonsda-light-form'),
        'manage_options',
        LLF_SLUG . '-translations',
        ['\LonsdaLightForm\Admin', 'renderTranslations']
    );

    add_submenu_page(
        LLF_SLUG,
        __('Self Tests', 'lonsda-light-form'),
        __('Self Tests', 'lonsda-light-form'),
        'manage_options',
        LLF_SLUG . '-tests',
        ['\LonsdaLightForm\Admin', 'renderTests']
    );

    add_submenu_page(
        LLF_SLUG,
        __('Help', 'lonsda-light-form'),
        __('Help', 'lonsda-light-form'),
        'manage_options',
        LLF_SLUG . '-help',
        ['\LonsdaLightForm\Admin', 'renderHelp']
    );
}, 5);

add_action('init', ['\LonsdaLightForm\Admin', 'init']);
add_action('admin_init', ['\LonsdaLightForm\Admin', 'handleTranslationActions']);
add_action('admin_init', ['\LonsdaLightForm\Admin', 'handleEntryActions']);
add_action('admin_post_llf_export', ['\LonsdaLightForm\Admin', 'handleExport']);
add_action('admin_post_llf_import', ['\LonsdaLightForm\Admin', 'handleImport']);
add_action('admin_post_llf_recaptcha_test', ['\LonsdaLightForm\Admin', 'handleRecaptchaTest']);
add_action('admin_enqueue_scripts', ['\LonsdaLightForm\Admin', 'enqueueSettings']);

\LonsdaLightForm\Translations::init();
\LonsdaLightForm\Entries::init();
\LonsdaLightForm\Notifications::init();
\LonsdaLightForm\Forms::init();
\LonsdaLightForm\FormBuilder::init();
\LonsdaLightForm\Submission::init();
\LonsdaLightForm\Shortcode::init();
\LonsdaLightForm\Block::init();

// Applied on every request, but the runner returns immediately once there is
// nothing outstanding.
// init 20, not plugins_loaded: Carbon Fields registers its fields on init at
// priority 0, and a migration that rebuilds form definitions has to be able to
// read them. Running earlier meant reading nothing and writing that back.
add_action('init', ['\LonsdaLightForm\Migrations', 'run'], 20);

// A fresh install has no data to migrate, so the table is created directly and
// the history is marked as already applied.
register_activation_hook(__FILE__, ['\LonsdaLightForm\Migrations', 'activate']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('lonsda-light-form', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
