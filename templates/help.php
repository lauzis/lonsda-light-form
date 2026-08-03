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

    <p class="description" style="max-width:860px;">
        <?php esc_html_e('The first section is the short path from nothing to a working form. Everything after it is reference — what each setting does, and why it behaves the way it does.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Getting started', 'lonsda-light-form'); ?></h2>
    <ol style="max-width:860px;">
        <li>
            <?php
            printf(
                /* translators: %s: link to the add form screen */
                wp_kses(
                    __('Go to <a href="%s">Forms &rarr; Add Form</a> and give it a title. The title is how you will find it later, and it becomes the notification subject.', 'lonsda-light-form'),
                    ['a' => ['href' => []]]
                ),
                esc_url(admin_url('post-new.php?post_type=' . \LonsdaLightForm\Forms::POST_TYPE))
            );
            ?>
        </li>
        <li><?php esc_html_e('On the Fields tab, add a row per input. Give each a Label — everything else has a sensible default. Rows are collapsed to their labels, so drag them to reorder without opening them.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Publish. The form is now usable; the other tabs are refinements you can come back to.', 'lonsda-light-form'); ?></li>
        <li>
            <?php
            printf(
                /* translators: %s: link to the forms list */
                wp_kses(
                    __('Open <a href="%s">Forms</a> and note the id in the first column. That is the form id, and it is not the number in the address bar while editing — see below.', 'lonsda-light-form'),
                    ['a' => ['href' => []]]
                ),
                esc_url(admin_url('admin.php?page=' . LLF_SLUG))
            );
            ?>
        </li>
        <li><?php esc_html_e('Edit the page the form belongs on and insert the Lonsda Form block, choosing the form from the dropdown. Or paste the shortcode with that id.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Submit it yourself once. The answer appears under Entries, and a notification goes to the address on the Notifications tab.', 'lonsda-light-form'); ?></li>
    </ol>

    <h3><?php esc_html_e('Two ids, and which one you need', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('A form is edited as a post but rendered from its own table, so it has a post id and a form id. The Forms list, the shortcode and the block all use the form id. The post id only ever appears in the address bar while editing, and using it renders nothing — if you do, the message on the page names the id you should have used instead.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('What is on each tab', 'lonsda-light-form'); ?></h3>
    <table class="widefat striped" style="max-width:860px;margin-bottom:20px;">
        <tbody>
            <tr><th style="width:170px;"><?php esc_html_e('Fields', 'lonsda-light-form'); ?></th><td><?php esc_html_e('The inputs, in the order they appear.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Submit button', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Its wording, and the key that translates it.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Confirmation', 'lonsda-light-form'); ?></th><td><?php esc_html_e('What is shown after a successful submission, and whether the form is hidden.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Notifications', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Who is emailed, and whether entries are kept.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Protection', 'lonsda-light-form'); ?></th><td><?php esc_html_e('reCAPTCHA, once it is configured in Settings.', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Building a form', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('A form is a title and a list of fields. Add one from Forms → Add Form: the title names it for you in the admin, and each row in the Fields list becomes one input.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('What a field carries', 'lonsda-light-form'); ?></h3>
    <p>
        <?php esc_html_e('The same metadata is gathered for every form and passed to both hooks — a rejection is often the more interesting one to look at. post_id is null when the form is not on a post at all, such as in a footer. The client IP is REMOTE_ADDR only: forwarding headers are set by whoever sent the request unless a proxy overwrites them, so trusting one would let a submitter choose the address recorded against them. Behind such a proxy, supply it through the context filter below. A submission rejected for a bad nonce or a tripped spam check does not reach either hook.', 'lonsda-light-form'); ?>
    </p>
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
    <p>
        <?php esc_html_e('Use the id from the Forms list, which is not the same as the id in the address bar when you edit a form. A form is edited as a post and rendered from its own table, so it has both; the Forms list shows the one to use. Giving the wrong one produces a message naming the right one, visible to administrators only.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Entries and notifications', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('Submissions are kept in the database and listed under Entries, where they can be filtered by form or status, opened to see every answer with the page, language and IP address, deleted, or downloaded as CSV. Keeping them is on by default: a notification that never arrives is otherwise a lost enquiry, and mail is the part most likely to break quietly.', 'lonsda-light-form'); ?>
    </p>
    <p>
        <?php esc_html_e('A new form arrives with its Notifications tab prefilled: the site administration address from Settings → General, and the subject {form_title}, which becomes the form name when the mail is sent. Both are ordinary fields — change them, or clear the address to send nothing. Naming a field in Reply-To makes replies go to whoever submitted the form. An entry keeps each label and type next to its value, so it stays readable after the form has been changed.', 'lonsda-light-form'); ?>
    </p>

    <p>
        <?php esc_html_e('An entry arrives marked New and becomes Viewed when you open it — opening it is the only evidence of it being read that exists. The number still unread appears beside Entries in the menu, so a submission does not sit there unnoticed. If you open one by accident, Mark unread puts it back.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('What you can put in a notification', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('The subject and the message both accept placeholders. Leave the message empty and it lists every field and its answer; write your own and you decide what goes in it.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <strong><?php esc_html_e('Any field can be used by its Name.', 'lonsda-light-form'); ?></strong>
        <?php esc_html_e('A field named surname becomes {surname}. That is the Name on the field, not its Label — the Label is what the visitor reads and may be several words. A field left blank comes out as nothing, and a checkbox as Yes or No.', 'lonsda-light-form'); ?>
    </p>

    <table class="widefat striped" style="max-width:860px;margin-bottom:12px;">
        <thead>
            <tr>
                <th style="width:200px;"><?php esc_html_e('Placeholder', 'lonsda-light-form'); ?></th>
                <th><?php esc_html_e('Replaced by', 'lonsda-light-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>{<?php esc_html_e('field_name', 'lonsda-light-form'); ?>}</code></td><td><?php esc_html_e('That field\'s answer — {surname} for a field named surname.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{all_fields}</code></td><td><?php esc_html_e('Every field and its answer, one per line, in the order they appear on the form.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{form_title}</code></td><td><?php esc_html_e('The form\'s title.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{site_name}</code></td><td><?php esc_html_e('The site title.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{site_url}</code></td><td><?php esc_html_e('The site address.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{submitted_at}</code></td><td><?php esc_html_e('When it was submitted, in UTC.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{page_title}</code></td><td><?php esc_html_e('The page the form was on. Empty if it was not on one.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{page_url}</code></td><td><?php esc_html_e('That page\'s address.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{language}</code></td><td><?php esc_html_e('Language the page was in.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{ip}</code></td><td><?php esc_html_e('The submitter\'s IP address.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{user_agent}</code></td><td><?php esc_html_e('The browser they used.', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>

    <p style="max-width:860px;">
        <?php esc_html_e('A field whose Name matches one of the fixed placeholders does not displace it — {site_name} always means the site, on every form.', 'lonsda-light-form'); ?>
    </p>

    <p><strong><?php esc_html_e('For example', 'lonsda-light-form'); ?></strong></p>
    <pre style="background:#f6f7f7;padding:12px;max-width:860px;overflow:auto;"><code><?php
        echo esc_html(
            __('Subject:', 'lonsda-light-form') . ' ' . __('Enquiry from {surname} via {site_name}', 'lonsda-light-form') . "\n\n"
            . __('Message:', 'lonsda-light-form') . "\n"
            . __('{surname} got in touch through {page_title}.', 'lonsda-light-form') . "\n\n"
            . '{all_fields}' . "\n\n"
            . __('Reply to them at {email}. Received {submitted_at} UTC.', 'lonsda-light-form')
        );
    ?></code></pre>

    <h2><?php esc_html_e('After a submission', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('Each form has its own confirmation message, edited on the form itself, and a setting for whether the form is hidden once it has been accepted. Hiding is on by default: leaving a filled-in form on screen under a thank-you reads as though nothing was sent, and invites a second submission. Switch it off to leave the form in place.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Translating the fields', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php esc_html_e('Labels, the submit button and the confirmation are typed into the form editor, so they live in the database rather than in the plugin. The tools that normally collect translatable text scan source files and cannot see them. Each string therefore carries a key of its own.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('Where the keys come from', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('A key is filled in from the field name: a field named email gets field_email_label. It keeps in step with the name, so renaming the field renames the key and nothing has to be tidied up by hand.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('Change a key yourself and it stops following. That is deliberate — a key you chose may already be referred to somewhere else, so a later rename must not quietly change it out from under you. Clearing the box hands it back to automatic control and it is regenerated from the current name.', 'lonsda-light-form'); ?>
    </p>
    <table class="widefat striped" style="max-width:860px;margin-bottom:20px;">
        <thead>
            <tr>
                <th style="width:30%;"><?php esc_html_e('What you do', 'lonsda-light-form'); ?></th>
                <th><?php esc_html_e('What the key does', 'lonsda-light-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><?php esc_html_e('Name a field "email"', 'lonsda-light-form'); ?></td><td><code>field_email_label</code></td></tr>
            <tr><td><?php esc_html_e('Rename it to "mail"', 'lonsda-light-form'); ?></td><td><code>field_mail_label</code> — <?php esc_html_e('follows', 'lonsda-light-form'); ?></td></tr>
            <tr><td><?php esc_html_e('Type your own key', 'lonsda-light-form'); ?></td><td><?php esc_html_e('kept, and left alone from then on', 'lonsda-light-form'); ?></td></tr>
            <tr><td><?php esc_html_e('Clear the box', 'lonsda-light-form'); ?></td><td><?php esc_html_e('back to automatic, regenerated from the name', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>

    <h3><?php esc_html_e('Translating them here', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php
        printf(
            /* translators: %s: link to the translations page */
            wp_kses(
                __('Open <a href="%s">Translations</a>, choose a language and a form, and fill in the boxes. Saving writes both a .mo, which is what the site reads, and a .po, which is the editable source — so a translation started here can be carried on in Poedit or handed to someone else, and one done elsewhere can be uploaded back.', 'lonsda-light-form'),
                ['a' => ['href' => []]]
            ),
            esc_url(admin_url('admin.php?page=' . LLF_SLUG . '-translations'))
        );
        ?>
    </p>
    <ul style="list-style:disc;padding-left:22px;max-width:860px;">
        <li><?php esc_html_e('An empty box means untranslated, and the original wording is shown instead. Nothing ever renders as a bare key.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Clearing a box that had a translation removes it.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Saving one form does not disturb translations for another — only what is on screen is changed, and the rest of the file is kept.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('The language list offers whatever the site has: the languages from WPML or Polylang if either is running, plus any WordPress translations installed.', 'lonsda-light-form'); ?></li>
    </ul>
    <p style="max-width:860px;">
        <?php
        printf(
            /* translators: %s: directory path */
            esc_html__('Files are kept in %s rather than inside the plugin, because WordPress replaces the plugin folder on every update and would take them with it. You can drop files there over FTP just as well as using the page.', 'lonsda-light-form'),
            '<code>' . esc_html(str_replace(ABSPATH, '', \LonsdaLightForm\Translations::directory())) . '</code>'
        );
        ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('A file has to be named for the locale WordPress serves the page in, or it is never looked for. The Translations page shows the current locale and lists what is installed, so a file that is being ignored is visible rather than mysterious.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('If the site runs WPML', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('Strings are handed to WPML String Translation when a form is saved, so a translator sees them before anyone has visited the page they are on. WPML is consulted first and these files fill in whatever it has no translation for, so the two can be used together and neither has to be turned off.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Handling submissions', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('This plugin validates a submission and then hands it on — it does not store or email anything itself. Hook the action below from a theme or a companion plugin to decide what happens:', 'lonsda-light-form'); ?>
    </p>
    <pre style="background:#f6f7f7;padding:12px;max-width:900px;overflow:auto;"><code>add_action( '<?php echo esc_html(\LonsdaLightForm\Submission::HOOK_SUBMITTED); ?>', function ( $values, $form, $context ) {
    // $values  — field name => submitted value, sanitised and validated
    // $form    — the stored definition: id, title, settings
    // $context — form_id, post_id, language, time, submitted_at, ip, user_agent
    wp_mail( get_option( 'admin_email' ), 'New submission: ' . $form['title'], print_r( $values, true ) );
}, 10, 3 );</code></pre>
    <table class="widefat striped" style="max-width:900px;">
        <tbody>
            <tr>
                <td style="width:230px;"><code><?php echo esc_html(\LonsdaLightForm\Submission::HOOK_SUBMITTED); ?></code></td>
                <td><?php esc_html_e('Fired once a submission has passed every check.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(\LonsdaLightForm\Submission::FILTER_CONTEXT); ?></code></td>
                <td><?php esc_html_e('Filters the metadata above — to add keys of your own, or to supply the real client IP on a site behind a proxy.', 'lonsda-light-form'); ?></td>
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
