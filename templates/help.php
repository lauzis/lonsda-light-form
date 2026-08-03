<?php
/**
 * Help page.
 */

defined('ABSPATH') || exit;

$llf_recaptcha_ready = \LonsdaLightForm\FormBuilder::recaptchaConfigured();
$llf_settings_url    = admin_url('admin.php?page=' . LLF_SLUG . '-settings');
?>
<div class="wrap">
    <h1><?php esc_html_e('Lonsda Light Form — Help', 'lonsda-light-form'); ?></h1>

    <h2><?php esc_html_e('Building a form', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('A form is a title and a list of fields. Add one from Forms → Add Form: the title names it for you in the admin, and each row in the Fields list becomes one input.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('What a field carries', 'lonsda-light-form'); ?></h3>
    <table class="widefat striped" style="max-width:900px;">
        <tbody>
            <tr>
                <td style="width:170px;"><strong><?php esc_html_e('Label', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Shown above the input. Required — a row without one is discarded when the form is saved.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Name', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Identifier used when the submission is stored or emailed. Leave it blank and it is derived from the label. Two fields cannot share a name — a duplicate is given a numeric suffix, because otherwise one would silently overwrite the other in the submission.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Type', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Text for a single line, Text area for longer answers, or Checkbox for a single tick box.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Placeholder', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Hint shown inside the empty input. The label stays visible either way — a placeholder disappears as soon as someone types, so it is a poor substitute for one. Not offered for a checkbox, which has nothing to put it in.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Ticked by default', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Whether a checkbox starts ticked. Leave it off for anything the visitor is meant to actively agree to.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Required', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('The submission is rejected when the field is left empty.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Validation', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('None, an email address, or a custom pattern. Only offered for single-line text: an email check on a multi-line box would never be useful, and is cleared if you switch the type afterwards.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Pattern', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('A regular expression the value must match, written without delimiters — for example [A-Z]{2}[0-9]{4}. Appears only when Validation is set to a custom pattern.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Maximum length', 'lonsda-light-form'); ?></strong></td>
                <td><?php esc_html_e('Longest accepted value, in characters. Blank or zero means no limit.', 'lonsda-light-form'); ?></td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('reCAPTCHA', 'lonsda-light-form'); ?></h2>
    <?php if ($llf_recaptcha_ready) : ?>
        <p>
            <?php esc_html_e('Both keys are configured, so each form has a "Protect with reCAPTCHA" option. Turn it on per form rather than globally — a short internal form rarely needs it, a public contact form usually does.', 'lonsda-light-form'); ?>
        </p>
    <?php else : ?>
        <p>
            <?php
            printf(
                /* translators: %s: link to the settings page */
                esc_html__('The per-form reCAPTCHA option is hidden because no keys are set. Fill both in under %s and it appears on every form. The option stays hidden until then deliberately: an option that cannot work invites you to switch it on and assume you are protected.', 'lonsda-light-form'),
                '<a href="' . esc_url($llf_settings_url) . '">' . esc_html__('Settings', 'lonsda-light-form') . '</a>'
            );
            ?>
        </p>
    <?php endif; ?>

    <h2><?php esc_html_e('Putting a form on a page', 'lonsda-light-form'); ?></h2>
    <p><?php esc_html_e('Two ways, both showing the same form:', 'lonsda-light-form'); ?></p>
    <ul style="list-style:disc;padding-left:22px;max-width:900px;">
        <li>
            <?php esc_html_e('The Lonsda Form block — insert it and pick a form from the list.', 'lonsda-light-form'); ?>
        </li>
        <li>
            <?php
            printf(
                /* translators: %s: shortcode example */
                esc_html__('The shortcode %s, using the id from the Forms list.', 'lonsda-light-form'),
                '<code>[' . esc_html(\LonsdaLightForm\Shortcode::TAG) . ' id="1"]</code>'
            );
            ?>
        </li>
    </ul>
    <p>
        <?php esc_html_e('Both render the form at the moment the page is viewed rather than freezing a copy into the content, so editing a form updates it everywhere it appears.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Handling submissions', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('This plugin validates a submission and then hands it on — it does not store or email anything itself. Hook the action below from a theme or a companion plugin to decide what happens:', 'lonsda-light-form'); ?>
    </p>
    <pre style="background:#f6f7f7;padding:12px;max-width:900px;overflow:auto;"><code>add_action( '<?php echo esc_html(\LonsdaLightForm\Submission::HOOK_SUBMITTED); ?>', function ( $values, $form, $context ) {
    // $values  — field name => submitted value, sanitised and validated
    // $form    — the stored definition: id, title, settings
    // $context — post_id, ip, time
    wp_mail( get_option( 'admin_email' ), 'New submission: ' . $form['title'], print_r( $values, true ) );
}, 10, 3 );</code></pre>
    <table class="widefat striped" style="max-width:900px;">
        <tbody>
            <tr>
                <td style="width:230px;"><code><?php echo esc_html(\LonsdaLightForm\Submission::HOOK_SUBMITTED); ?></code></td>
                <td><?php esc_html_e('Fired once a submission has passed every check.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(\LonsdaLightForm\Submission::HOOK_REJECTED); ?></code></td>
                <td><?php esc_html_e('Fired when validation failed, with the errors. Useful for rate limiting or alerting.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(\LonsdaLightForm\Submission::FILTER_VALIDATE); ?></code></td>
                <td><?php esc_html_e('Filter to add errors of your own — an external blocklist, say — before the submission is accepted.', 'lonsda-light-form'); ?></td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Spam', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('Every form carries a hidden honeypot field and records when it was opened; a submission that fills the honeypot in, or arrives faster than the configured minimum, is refused. Both are configurable under Settings, and neither tells the sender which check it tripped — that would only help the next attempt.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Where forms are stored', 'lonsda-light-form'); ?></h2>
    <p>
        <?php
        printf(
            /* translators: %s: database table name */
            esc_html__('Each form is one row in %s, holding its title and its structure as JSON. That table is the record the front end reads, so rendering a form is a single indexed lookup.', 'lonsda-light-form'),
            '<code>' . esc_html(\LonsdaLightForm\Migrations::tableName()) . '</code>'
        );
        ?>
    </p>
    <p>
        <?php esc_html_e('Editing happens through a hidden post type, because that is what the field editor attaches to and it brings saving, nonces and capability checks with it. The row is rewritten from the post every time you save, and removed when the form is deleted.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Logging', 'lonsda-light-form'); ?></h2>
    <p>
        <?php
        printf(
            /* translators: %s: link to the settings page */
            esc_html__('Switch logging on under %s to record what the plugin did — forms saved or deleted, schema changes applied. Failures always reach PHP\'s error log regardless of the setting. The Logs page appears in the menu only while logging is on.', 'lonsda-light-form'),
            '<a href="' . esc_url($llf_settings_url) . '">' . esc_html__('Settings', 'lonsda-light-form') . '</a>'
        );
        ?>
    </p>

    <h2><?php esc_html_e('Upgrades', 'lonsda-light-form'); ?></h2>
    <p>
        <?php
        printf(
            /* translators: %s: the recorded data version */
            esc_html__('Schema changes are applied once each, in version order, the first time the updated plugin loads. This site has applied up to version %s.', 'lonsda-light-form'),
            '<code>' . esc_html(get_option('llf_data_version', __('nothing yet', 'lonsda-light-form'))) . '</code>'
        );
        ?>
    </p>
</div>
