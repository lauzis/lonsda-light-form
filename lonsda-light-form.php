<?php
/**
 * Plugin Name: Lonsda Light Form
 * Plugin URI:  https://github.com/lauzis/lonsda-light-form
 * Description: Lightweight Carbon Fields form builder.
 * Version:     0.1.0
 * Author:      Aivars Lauzis
 * Text Domain: lonsda-light-form
 * Domain Path: /languages
 * License:     MIT
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LLF_VERSION', '0.1.0');
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
}, 5);

add_action('init', ['\LonsdaLightForm\Admin', 'init']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('lonsda-light-form', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
