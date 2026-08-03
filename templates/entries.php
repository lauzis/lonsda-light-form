<?php
/**
 * Entries list.
 */

defined('ABSPATH') || exit;

use LonsdaLightForm\Entries;

$llf_form_id  = isset($_GET['llf_form']) ? (int) $_GET['llf_form'] : 0;
$llf_page     = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
$llf_per_page = 25;
$llf_total    = Entries::count($llf_form_id);
$llf_pages    = (int) ceil($llf_total / $llf_per_page);
$llf_rows     = Entries::all(['form_id' => $llf_form_id, 'per_page' => $llf_per_page, 'page' => $llf_page]);
$llf_forms    = Entries::formsWithEntries();
$llf_base     = admin_url('admin.php?page=' . LLF_SLUG . '-entries');
$llf_open     = isset($_GET['llf_entry']) ? (int) $_GET['llf_entry'] : 0;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Entries', 'lonsda-light-form'); ?></h1>

    <?php if ($llf_total) : ?>
        <a class="page-title-action"
           href="<?php echo esc_url(wp_nonce_url($llf_base . '&llf_action=csv&llf_form=' . $llf_form_id, 'llf-entries')); ?>">
            <?php esc_html_e('Download CSV', 'lonsda-light-form'); ?>
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if (!empty($_GET['llf_done'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Entry deleted.', 'lonsda-light-form'); ?></p></div>
    <?php endif; ?>

    <?php if ($llf_forms) : ?>
        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="<?php echo esc_attr(LLF_SLUG . '-entries'); ?>">
            <select name="llf_form">
                <option value="0"><?php esc_html_e('All forms', 'lonsda-light-form'); ?></option>
                <?php foreach ($llf_forms as $llf_id => $llf_label) : ?>
                    <option value="<?php echo esc_attr($llf_id); ?>" <?php selected($llf_id, $llf_form_id); ?>>
                        <?php echo esc_html($llf_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button(__('Filter', 'lonsda-light-form'), 'secondary', '', false); ?>
        </form>
    <?php endif; ?>

    <?php if (!$llf_rows) : ?>
        <p>
            <?php if ($llf_form_id) : ?>
                <em><?php esc_html_e('No entries for that form yet.', 'lonsda-light-form'); ?></em>
            <?php else : ?>
                <em><?php esc_html_e('No entries yet. They appear here once a form is submitted, as long as the form is set to keep them — see the Notifications tab when editing a form.', 'lonsda-light-form'); ?></em>
            <?php endif; ?>
        </p>
    <?php else : ?>
        <p class="description">
            <?php
            printf(
                /* translators: %d: number of entries */
                esc_html(_n('%d entry.', '%d entries.', $llf_total, 'lonsda-light-form')),
                (int) $llf_total
            );
            ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px;"><?php esc_html_e('ID', 'lonsda-light-form'); ?></th>
                    <th style="width:20%;"><?php esc_html_e('Form', 'lonsda-light-form'); ?></th>
                    <th style="width:150px;"><?php esc_html_e('Submitted (UTC)', 'lonsda-light-form'); ?></th>
                    <th><?php esc_html_e('Summary', 'lonsda-light-form'); ?></th>
                    <th style="width:150px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($llf_rows as $llf_row) : ?>
                    <?php
                    $llf_entry   = Entries::decode($llf_row);
                    $llf_summary = [];

                    foreach ($llf_entry['fields'] as $llf_field) {
                        $llf_value = $llf_field['value'];

                        if ('checkbox' === $llf_field['type']) {
                            $llf_value = $llf_value
                                ? __('Yes', 'lonsda-light-form')
                                : __('No', 'lonsda-light-form');
                        }

                        $llf_value = trim((string) $llf_value);

                        if ('' !== $llf_value) {
                            $llf_summary[] = $llf_field['label'] . ': ' . $llf_value;
                        }
                    }

                    $llf_is_open = $llf_open === $llf_entry['id'];
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $llf_entry['id']); ?></td>
                        <td><?php echo esc_html($llf_entry['form_title']); ?></td>
                        <td><code><?php echo esc_html($llf_entry['submitted_at']); ?></code></td>
                        <td><?php echo esc_html(wp_trim_words(implode(' · ', $llf_summary), 18)); ?></td>
                        <td>
                            <a class="button button-small"
                               href="<?php echo esc_url($llf_is_open ? $llf_base . '&llf_form=' . $llf_form_id : add_query_arg('llf_entry', $llf_entry['id'], $llf_base . '&llf_form=' . $llf_form_id)); ?>">
                                <?php echo $llf_is_open ? esc_html__('Hide', 'lonsda-light-form') : esc_html__('View', 'lonsda-light-form'); ?>
                            </a>
                            <a class="button button-small"
                               href="<?php echo esc_url(wp_nonce_url($llf_base . '&llf_action=delete&llf_entry=' . $llf_entry['id'] . '&llf_form=' . $llf_form_id, 'llf-entries')); ?>"
                               onclick="return confirm('<?php echo esc_js(__('Delete this entry? This cannot be undone.', 'lonsda-light-form')); ?>');">
                                <?php esc_html_e('Delete', 'lonsda-light-form'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php if ($llf_is_open) : ?>
                        <tr>
                            <td colspan="5" style="background:#fbfbfb;">
                                <table class="widefat striped" style="margin:8px 0;">
                                    <tbody>
                                        <?php foreach ($llf_entry['fields'] as $llf_field) : ?>
                                            <tr>
                                                <th style="width:220px;"><?php echo esc_html($llf_field['label']); ?></th>
                                                <td>
                                                    <?php if ('checkbox' === $llf_field['type']) : ?>
                                                        <?php echo $llf_field['value'] ? esc_html__('Yes', 'lonsda-light-form') : esc_html__('No', 'lonsda-light-form'); ?>
                                                    <?php elseif ('' === trim((string) $llf_field['value'])) : ?>
                                                        <span style="color:#999;"><?php esc_html_e('(not answered)', 'lonsda-light-form'); ?></span>
                                                    <?php else : ?>
                                                        <?php echo nl2br(esc_html((string) $llf_field['value'])); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <th><?php esc_html_e('Page', 'lonsda-light-form'); ?></th>
                                            <td>
                                                <?php if ($llf_entry['post_id']) : ?>
                                                    <a href="<?php echo esc_url((string) get_permalink($llf_entry['post_id'])); ?>">
                                                        <?php echo esc_html(get_the_title($llf_entry['post_id']) ?: (string) $llf_entry['post_id']); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span style="color:#999;">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Language', 'lonsda-light-form'); ?></th>
                                            <td><code><?php echo esc_html($llf_entry['language'] ?: '—'); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('IP address', 'lonsda-light-form'); ?></th>
                                            <td><code><?php echo esc_html($llf_entry['ip'] ?: '—'); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('User agent', 'lonsda-light-form'); ?></th>
                                            <td style="word-break:break-all;"><?php echo esc_html($llf_entry['user_agent'] ?: '—'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($llf_pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%', $llf_base . '&llf_form=' . $llf_form_id),
                    'format'    => '',
                    'current'   => $llf_page,
                    'total'     => $llf_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
                ?>
            </div></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
