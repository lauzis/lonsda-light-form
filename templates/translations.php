<?php
/**
 * Translations page: get a POT out, put a MO or PO back.
 */

defined('ABSPATH') || exit;

use LonsdaLightForm\Translations;

$llf_strings   = Translations::strings();
$llf_installed = Translations::installed();
$llf_locales   = Translations::locales();
$llf_current   = determine_locale();
$llf_base      = admin_url('admin.php?page=' . LLF_SLUG . '-translations');
?>
<div class="wrap">
    <h1><?php esc_html_e('Lonsda Forms — Translations', 'lonsda-light-form'); ?></h1>

    <?php if (!empty($_GET['llf_error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(urldecode(wp_unslash($_GET['llf_error']))); ?></p></div>
    <?php elseif (!empty($_GET['llf_done'])) : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Saved.', 'lonsda-light-form'); ?></p></div>
    <?php endif; ?>

    <p class="description" style="max-width:840px;">
        <?php esc_html_e('Field labels and button text are typed into the form editor, so they live in the database rather than in the plugin and the usual scanning tools cannot see them. Download the POT below to get them all in one file, translate it, and upload the result.', 'lonsda-light-form'); ?>
    </p>
    <p class="description" style="max-width:840px;">
        <?php
        printf(
            /* translators: %s: directory path */
            esc_html__('Files are stored in %s, outside the plugin folder — WordPress replaces that folder on every update, which would take the translations with it. You can also drop files there directly over FTP.', 'lonsda-light-form'),
            '<code>' . esc_html(str_replace(ABSPATH, '', Translations::directory())) . '</code>'
        );
        ?>
    </p>
    <p class="description" style="max-width:840px;">
        <?php esc_html_e('If WPML is running, its String Translation is used first and these files fill in anything it has no translation for.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('1. Download the strings', 'lonsda-light-form'); ?></h2>
    <p>
        <?php
        printf(
            /* translators: %d: number of strings */
            esc_html(_n('%d string across all forms.', '%d strings across all forms.', count($llf_strings), 'lonsda-light-form')),
            count($llf_strings)
        );
        ?>
        <?php esc_html_e('This is a snapshot — download it again after adding or renaming a field.', 'lonsda-light-form'); ?>
    </p>
    <p>
        <a class="button button-primary"
           href="<?php echo esc_url(wp_nonce_url($llf_base . '&llf_action=download', 'llf-translations')); ?>">
            <?php esc_html_e('Download POT file', 'lonsda-light-form'); ?>
        </a>
    </p>

    <?php if ($llf_strings) : ?>
        <table class="wp-list-table widefat striped" style="max-width:900px;margin-bottom:24px;">
            <thead>
                <tr>
                    <th style="width:32%;"><?php esc_html_e('Translation key', 'lonsda-light-form'); ?></th>
                    <th style="width:30%;"><?php esc_html_e('Text', 'lonsda-light-form'); ?></th>
                    <th style="width:23%;"><?php esc_html_e('Used by', 'lonsda-light-form'); ?></th>
                    <th style="width:15%;"><?php esc_html_e('In this language', 'lonsda-light-form'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($llf_strings as $llf_key => $llf_entry) : ?>
                    <?php $llf_shown = \LonsdaLightForm\Strings::get($llf_entry['text'], $llf_key); ?>
                    <tr>
                        <td><code><?php echo esc_html($llf_key); ?></code></td>
                        <td><?php echo esc_html($llf_entry['text']); ?></td>
                        <td><?php echo esc_html(implode(', ', $llf_entry['forms'])); ?></td>
                        <td>
                            <?php if ($llf_shown === $llf_entry['text']) : ?>
                                <span style="color:#999;"><?php esc_html_e('not translated', 'lonsda-light-form'); ?></span>
                            <?php else : ?>
                                <?php echo esc_html($llf_shown); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><em><?php esc_html_e('No forms have any fields yet, so there is nothing to translate.', 'lonsda-light-form'); ?></em></p>
    <?php endif; ?>

    <h2><?php esc_html_e('2. Upload a translation', 'lonsda-light-form'); ?></h2>
    <p class="description">
        <?php esc_html_e('A .po is compiled to .mo on the way in, so either will do. The locale decides the file name, and it has to match the locale WordPress runs the page in — otherwise gettext never looks for it.', 'lonsda-light-form'); ?>
    </p>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($llf_base); ?>">
        <?php wp_nonce_field('llf-translations'); ?>
        <input type="hidden" name="llf_action" value="upload">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="llf_locale"><?php esc_html_e('Locale', 'lonsda-light-form'); ?></label></th>
                    <td>
                        <select name="llf_locale" id="llf_locale">
                            <?php foreach ($llf_locales as $llf_code => $llf_label) : ?>
                                <option value="<?php echo esc_attr($llf_code); ?>" <?php selected($llf_code, $llf_current); ?>>
                                    <?php echo esc_html($llf_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: current locale */
                                esc_html__('This page is currently being served as %s.', 'lonsda-light-form'),
                                '<code>' . esc_html($llf_current) . '</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="llf_file"><?php esc_html_e('File', 'lonsda-light-form'); ?></label></th>
                    <td><input type="file" name="llf_file" id="llf_file" accept=".mo,.po" required></td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Upload', 'lonsda-light-form')); ?>
    </form>

    <h2><?php esc_html_e('Installed files', 'lonsda-light-form'); ?></h2>

    <?php if (!$llf_installed) : ?>
        <p><em><?php esc_html_e('None yet.', 'lonsda-light-form'); ?></em></p>
    <?php else : ?>
        <table class="wp-list-table widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Locale', 'lonsda-light-form'); ?></th>
                    <th><?php esc_html_e('Strings', 'lonsda-light-form'); ?></th>
                    <th><?php esc_html_e('Updated (UTC)', 'lonsda-light-form'); ?></th>
                    <th><?php esc_html_e('Size', 'lonsda-light-form'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($llf_installed as $llf_locale => $llf_file) : ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html($llf_locale); ?></code>
                            <?php if ($llf_locale === $llf_current) : ?>
                                <strong>&nbsp;&larr; <?php esc_html_e('in use now', 'lonsda-light-form'); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $llf_file['entries']); ?></td>
                        <td><code><?php echo esc_html(gmdate('Y-m-d H:i', $llf_file['modified'])); ?></code></td>
                        <td><?php echo esc_html(size_format($llf_file['size'])); ?></td>
                        <td>
                            <a class="button button-small"
                               href="<?php echo esc_url(wp_nonce_url($llf_base . '&llf_action=delete&llf_locale=' . rawurlencode($llf_locale), 'llf-translations')); ?>"
                               onclick="return confirm('<?php echo esc_js(__('Remove this translation file?', 'lonsda-light-form')); ?>');">
                                <?php esc_html_e('Remove', 'lonsda-light-form'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
