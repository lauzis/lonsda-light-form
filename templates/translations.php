<?php
/**
 * Translations page: get a POT out, put a MO or PO back.
 */

defined('ABSPATH') || exit;

use LonsdaLightForm\Translations;

$llf_current     = determine_locale();
$llf_locales     = Translations::locales();
$llf_editing     = isset($_GET['llf_locale'])
    ? Translations::sanitizeLocale(wp_unslash($_GET['llf_locale']))
    : $llf_current;
$llf_form        = isset($_GET['llf_form']) ? (int) $_GET['llf_form'] : 0;
$llf_strings     = Translations::strings($llf_form);
$llf_all_strings = Translations::strings();
$llf_existing    = Translations::existing($llf_editing);
$llf_installed   = Translations::installed();

$llf_all_forms = [];

foreach (\LonsdaLightForm\Forms::all() as $llf_row) {
    $llf_all_forms[(int) $llf_row->id] = (string) $llf_row->title;
}
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

    <h2><?php esc_html_e('Translate', 'lonsda-light-form'); ?></h2>
    <p class="description">
        <?php esc_html_e('Pick a language and a form, fill in the boxes, and save. Both a .mo and a .po are written, so the same translation can be carried on in Poedit or handed to someone else.', 'lonsda-light-form'); ?>
    </p>

    <form method="get" style="margin:12px 0;">
        <input type="hidden" name="page" value="<?php echo esc_attr(LLF_SLUG . '-translations'); ?>">

        <label for="llf_locale_pick"><?php esc_html_e('Language', 'lonsda-light-form'); ?></label>
        <select name="llf_locale" id="llf_locale_pick">
            <?php foreach ($llf_locales as $llf_code => $llf_label) : ?>
                <option value="<?php echo esc_attr($llf_code); ?>" <?php selected($llf_code, $llf_editing); ?>>
                    <?php echo esc_html($llf_label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="llf_form_pick" style="margin-left:12px;"><?php esc_html_e('Form', 'lonsda-light-form'); ?></label>
        <select name="llf_form" id="llf_form_pick">
            <option value="0"><?php esc_html_e('All forms', 'lonsda-light-form'); ?></option>
            <?php foreach ($llf_all_forms as $llf_fid => $llf_ftitle) : ?>
                <option value="<?php echo esc_attr($llf_fid); ?>" <?php selected($llf_fid, $llf_form); ?>>
                    <?php echo esc_html($llf_ftitle); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php submit_button(__('Show', 'lonsda-light-form'), 'secondary', '', false); ?>
    </form>

    <?php if (!$llf_strings) : ?>
        <p><em><?php esc_html_e('Nothing to translate — no form here has any fields yet.', 'lonsda-light-form'); ?></em></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url($llf_base); ?>">
            <?php wp_nonce_field('llf-translations'); ?>
            <input type="hidden" name="llf_action" value="save">
            <input type="hidden" name="llf_locale" value="<?php echo esc_attr($llf_editing); ?>">
            <input type="hidden" name="llf_form" value="<?php echo esc_attr((string) $llf_form); ?>">

            <table class="wp-list-table widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th style="width:26%;"><?php esc_html_e('Original', 'lonsda-light-form'); ?></th>
                        <th style="width:38%;">
                            <?php
                            printf(
                                /* translators: %s: locale being edited */
                                esc_html__('Translation (%s)', 'lonsda-light-form'),
                                esc_html($llf_editing)
                            );
                            ?>
                        </th>
                        <th style="width:36%;"><?php esc_html_e('Key and where it is used', 'lonsda-light-form'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $llf_group = null; ?>
                    <?php foreach ($llf_strings as $llf_key => $llf_entry) : ?>
                        <?php
                        // A heading whenever the group changes. strings() sorts
                        // by group first, so each one is emitted exactly once.
                        $llf_this = $llf_entry['group'] ?? Translations::GROUP_FIELDS;

                        if ($llf_this !== $llf_group) :
                            $llf_group  = $llf_this;
                            $llf_labels = Translations::groups();
                            ?>
                            <tr class="llf-group">
                                <th colspan="3">
                                    <?php echo esc_html($llf_labels[$llf_group] ?? $llf_group); ?>
                                </th>
                            </tr>
                        <?php endif; ?>

                        <tr>
                            <td><strong><?php echo esc_html($llf_entry['text']); ?></strong></td>
                            <td>
                                <input type="text" class="large-text"
                                       name="llf_tr[<?php echo esc_attr($llf_key); ?>]"
                                       value="<?php echo esc_attr($llf_existing[$llf_key] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($llf_entry['text']); ?>">
                            </td>
                            <td>
                                <code><?php echo esc_html($llf_key); ?></code><br>
                                <span class="description"><?php echo esc_html(implode(', ', $llf_entry['forms'])); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description" style="max-width:840px;">
                <?php esc_html_e('An empty box means untranslated — the original is shown instead. Clearing a box that had a translation removes it. Translations for forms not listed here are kept.', 'lonsda-light-form'); ?>
            </p>

            <?php submit_button(__('Save translations', 'lonsda-light-form')); ?>
        </form>
    <?php endif; ?>

    <h2><?php esc_html_e('Download', 'lonsda-light-form'); ?></h2>
    <p>
        <?php
        printf(
            /* translators: %d: number of strings */
            esc_html(_n('%d string across all forms.', '%d strings across all forms.', count($llf_all_strings), 'lonsda-light-form')),
            count($llf_all_strings)
        );
        ?>
        <?php esc_html_e('The POT is a snapshot of the originals, for translating outside WordPress — download it again after adding or renaming a field.', 'lonsda-light-form'); ?>
    </p>
    <p>
        <a class="button"
           href="<?php echo esc_url(wp_nonce_url($llf_base . '&llf_action=download', 'llf-translations')); ?>">
            <?php esc_html_e('Download POT file', 'lonsda-light-form'); ?>
        </a>
    </p>

    <h2><?php esc_html_e('Upload a translation', 'lonsda-light-form'); ?></h2>
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

<style>
    /* A heading row rather than separate tables: one form still posts the lot,
       and the striping stays continuous down the column. */
    .llf-group th {
        padding-top: 22px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #50575e;
        border-bottom: 1px solid #dcdcde;
    }
    .llf-group:first-child th { padding-top: 6px; }
</style>
