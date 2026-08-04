<?php
/**
 * Forms list.
 *
 * Reads the custom table rather than the post type: the table is the canonical
 * runtime record, and this is the same read the front end would do.
 *
 * @var object[] $forms
 */

defined('ABSPATH') || exit;

$llf_add_url = admin_url('post-new.php?post_type=' . \LonsdaLightForm\Forms::POST_TYPE);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Forms', 'lonsda-light-form'); ?></h1>
    <a href="<?php echo esc_url($llf_add_url); ?>" class="page-title-action"><?php esc_html_e('Add Form', 'lonsda-light-form'); ?></a>
    <hr class="wp-header-end">

    <?php if (empty($forms)) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php esc_html_e('No forms yet.', 'lonsda-light-form'); ?>
                <a href="<?php echo esc_url($llf_add_url); ?>"><?php esc_html_e('Add your first one', 'lonsda-light-form'); ?></a>.
            </p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e('ID', 'lonsda-light-form'); ?></th>
                    <th><?php esc_html_e('Title', 'lonsda-light-form'); ?></th>
                    <th style="width:90px;"><?php esc_html_e('Fields', 'lonsda-light-form'); ?></th>
                    <th style="width:120px;"><?php esc_html_e('reCAPTCHA', 'lonsda-light-form'); ?></th>
                    <th style="width:170px;"><?php esc_html_e('Last updated (UTC)', 'lonsda-light-form'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $llf_form) : ?>
                    <?php
                    $llf_settings = json_decode((string) $llf_form->settings, true);
                    $llf_settings = is_array($llf_settings) ? $llf_settings : [];
                    $llf_edit     = get_edit_post_link((int) $llf_form->post_id);
                    ?>
                    <tr>
                        <td><?php echo (int) $llf_form->id; ?></td>
                        <td>
                            <strong>
                                <?php if ($llf_edit) : ?>
                                    <a href="<?php echo esc_url($llf_edit); ?>"><?php echo esc_html($llf_form->title ?: __('(no title)', 'lonsda-light-form')); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($llf_form->title ?: __('(no title)', 'lonsda-light-form')); ?>
                                <?php endif; ?>
                            </strong>
                        </td>
                        <td><?php echo (int) count($llf_settings['fields'] ?? []); ?></td>
                        <td>
                            <?php
                            echo empty($llf_settings['recaptcha'])
                                ? '<span style="color:#999;">&mdash;</span>'
                                : esc_html__('On', 'lonsda-light-form');
                            ?>
                        </td>
                        <td><code><?php echo esc_html($llf_form->updated_at); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
