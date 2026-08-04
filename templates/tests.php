<?php
/**
 * Self tests page.
 *
 * Runs against this install rather than a fixture, so what it reports is what
 * the site actually does.
 */

defined('ABSPATH') || exit;

use LonsdaLightForm\Tests;

$llf_scenarios = Tests::scenarios();
$llf_selected  = [];

if (!empty($_POST['llf_run']) && is_array($_POST['llf_run'])) {
    check_admin_referer('llf-run-tests');

    $llf_selected = array_intersect(
        array_map('sanitize_key', wp_unslash($_POST['llf_run'])),
        array_keys($llf_scenarios)
    );
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Lonsda Forms — Self Tests', 'lonsda-light-form'); ?></h1>

    <p class="description" style="max-width:820px;">
        <?php esc_html_e('These run against this site: the real database, the real post type and the real submission handler. That is the point — the failures worth finding are the ones a fixture cannot reproduce, such as a missing table or another plugin filtering the query.', 'lonsda-light-form'); ?>
    </p>
    <p class="description" style="max-width:820px;">
        <?php
        printf(
            /* translators: %s: the title prefix used by test forms */
            esc_html__('Each run creates forms titled %s and removes them again afterwards, whether it passed or not. Nothing else on the site is touched, and no email is sent.', 'lonsda-light-form'),
            '<code>' . esc_html(Tests::PREFIX) . '</code>'
        );
        ?>
    </p>

    <form method="post">
        <?php wp_nonce_field('llf-run-tests'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Scenarios', 'lonsda-light-form'); ?></th>
                    <td>
                        <fieldset>
                            <?php foreach ($llf_scenarios as $llf_slug => $llf_label) : ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="llf_run[]"
                                           value="<?php echo esc_attr($llf_slug); ?>"
                                        <?php checked(in_array($llf_slug, $llf_selected, true)); ?>>
                                    <?php echo esc_html($llf_label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Run selected tests', 'lonsda-light-form')); ?>
    </form>

    <?php if ($llf_selected) : ?>
        <hr>
        <div class="llf-test-results">
            <?php foreach ($llf_selected as $llf_slug) : ?>
                <?php Tests::run($llf_slug); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .llf-test-results h2 { margin-top: 28px; }
    .llf-test-title { margin: 16px 0 4px; font-size: 13px; color: #50575e; }
    .llf-test { margin: 0 0 4px; padding: 6px 10px; border-left: 4px solid; background: #fff; }
    .llf-test--pass { border-color: #00a32a; }
    .llf-test--fail { border-color: #d63638; }
    .llf-test--pass strong { color: #00a32a; }
    .llf-test--fail strong { color: #d63638; }
    .llf-test-debug { background: #f6f7f7; padding: 10px; overflow: auto; max-height: 320px; margin: 0 0 10px; }
</style>
