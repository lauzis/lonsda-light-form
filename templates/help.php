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

    <h2><?php esc_html_e('Why this plugin exists', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php esc_html_e('Gudlenieks, the site this plugin was written for, ran on Gravity Forms — an excellent plugin, and nothing here is a complaint about it. When the licence came up for renewal we looked at what was actually being used, and it was a very small part of what it offers: receive a contact form, store what was submitted, send an email about it.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('Conditional logic, multi-page forms, payment gateways, calculations, the integrations directory — all real and well built, and none of it in use on Gudlenieks. So this does that narrow part and nothing else. It is not a replacement for Gravity Forms and should not be chosen over it by anyone using more than a fraction of it; the table below makes that easy to check.', 'lonsda-light-form'); ?>
    </p>
    <table class="widefat striped" style="max-width:860px;margin-bottom:20px;">
        <thead>
            <tr>
                <th style="width:26%;"></th>
                <th style="width:37%;"><?php esc_html_e('This plugin', 'lonsda-light-form'); ?></th>
                <th><?php esc_html_e('Gravity Forms', 'lonsda-light-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><th><?php esc_html_e('Field types', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Text, text area, checkbox', 'lonsda-light-form'); ?></td><td><?php esc_html_e('Thirty or so, including uploads, dates and payments', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Validation', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Required, email, pattern, maximum length', 'lonsda-light-form'); ?></td><td><?php esc_html_e('The same, plus custom validators', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Conditional logic', 'lonsda-light-form'); ?></th><td>&mdash;</td><td><?php esc_html_e('Fields, pages and notifications', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Multi-page forms', 'lonsda-light-form'); ?></th><td>&mdash;</td><td><?php esc_html_e('Yes', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Entries', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Stored, filterable, CSV export', 'lonsda-light-form'); ?></td><td><?php esc_html_e('The same, plus notes, editing and partial entries', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Notifications', 'lonsda-light-form'); ?></th><td><?php esc_html_e('One per form', 'lonsda-light-form'); ?></td><td><?php esc_html_e('Many per form, routed conditionally', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Auto reply', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Yes', 'lonsda-light-form'); ?></td><td><?php esc_html_e('Yes', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Spam', 'lonsda-light-form'); ?></th><td><?php esc_html_e('Honeypot, timing, reCAPTCHA v2', 'lonsda-light-form'); ?></td><td><?php esc_html_e('The same, plus Akismet and v3', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Integrations', 'lonsda-light-form'); ?></th><td><?php esc_html_e('One action hook', 'lonsda-light-form'); ?></td><td><?php esc_html_e('Mailchimp, Stripe, Zapier and many more', 'lonsda-light-form'); ?></td></tr>
            <tr><th><?php esc_html_e('Licence', 'lonsda-light-form'); ?></th><td><?php esc_html_e('None', 'lonsda-light-form'); ?></td><td><?php esc_html_e('Annual', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>
    <p style="max-width:860px;">
        <?php esc_html_e('The right-hand column is why Gravity Forms costs what it does, and it is worth the money to anyone using it. If you need any of that, buy it — this is not a substitute. The point of the table is the left-hand column: on Gudlenieks, that narrow list was the whole requirement.', 'lonsda-light-form'); ?>
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
    <p style="max-width:860px;">
        <?php
        printf(
            /* translators: 1: link to create keys, 2: link to the reCAPTCHA console */
            wp_kses(
                __('Keys come from Google, not from here. Register the site at <a href="%1$s" target="_blank" rel="noopener noreferrer">google.com/recaptcha/admin/create</a> — choose <strong>reCAPTCHA v2</strong> with the "I\'m not a robot" tick box, add this site\'s domain, and it gives you a Site Key and a Secret Key. Keys you already have are listed at <a href="%2$s" target="_blank" rel="noopener noreferrer">google.com/recaptcha/admin</a>.', 'lonsda-light-form'),
                ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => []]
            ),
            'https://www.google.com/recaptcha/admin/create',
            'https://www.google.com/recaptcha/admin'
        );
        ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('Only v2 with the tick box is supported. v3 scores requests instead of challenging them and needs a threshold to judge against, which is a different feature rather than a different key.', 'lonsda-light-form'); ?>
    </p>
    <?php if ($llf_recaptcha_ready) : ?>
        <p>
            <?php
            printf(
                /* translators: %s: link to the settings page */
                wp_kses(
                    __('Both keys are set under <a href="%s">Settings</a>, so each form has a "Protect with reCAPTCHA" option. Turn it on per form rather than globally — a short internal form rarely needs it, a public contact form usually does.', 'lonsda-light-form'),
                    ['a' => ['href' => []]]
                ),
                esc_url($llf_settings_url)
            );
            ?>
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
        <?php esc_html_e('Submissions are kept in the database and listed under Entries, where they can be filtered by form, status or language, sorted by any column, opened to see every answer with the page, language and IP address, deleted, or downloaded as CSV. Keeping them is on by default: a notification that never arrives is otherwise a lost enquiry, and mail is the part most likely to break quietly.', 'lonsda-light-form'); ?>
    </p>
    <p>
        <?php esc_html_e('A new form arrives with its Notifications tab prefilled: the site administration address from Settings → General, and the subject {form_title}, which becomes the form name when the mail is sent. Both are ordinary fields — change them, or clear the address to send nothing. Naming a field in Reply-To makes replies go to whoever submitted the form. An entry keeps each label and type next to its value, so it stays readable after the form has been changed.', 'lonsda-light-form'); ?>
    </p>

    <p>
        <?php esc_html_e('An entry arrives marked New and becomes Viewed when you open it — opening it is the only evidence of it being read that exists. The number still unread appears beside Entries in the menu, so a submission does not sit there unnoticed. If you open one by accident, Mark unread puts it back.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('What you can put in a notification', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('The subject and the message both accept placeholders. Leave the message empty and it lists every field and its answer; write your own and you decide what goes in it. While editing a form, the Placeholders panel beside it lists everything available for that form — including its own fields — and copies a token when you click it.', 'lonsda-light-form'); ?>
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
            <tr><td><code>{submission_details}</code></td><td><?php esc_html_e('Where and when it came from — page, language, IP, timestamp — leaving out whatever it does not have.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{all_fields}</code></td><td><?php esc_html_e('Every field and its answer in form order — the label in bold, the answer on the line beneath it, a blank line before the next.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{form_title}</code></td><td><?php esc_html_e('The form\'s title.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{site_name}</code></td><td><?php esc_html_e('The site title.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{site_url}</code></td><td><?php esc_html_e('The site address.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{submitted_at}</code></td><td><?php esc_html_e('When it was submitted, in UTC.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{page_title}</code></td><td><?php esc_html_e('The page the form was on. Empty if it was not on one.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{page_url}</code></td><td><?php esc_html_e('That page\'s address.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{language}</code></td><td><?php esc_html_e('Language the page was in, as a bare code — lv.', 'lonsda-light-form'); ?></td></tr>
            <tr><td><code>{locale}</code></td><td><?php esc_html_e('The same language in full — lv_LV. This is the form translation files are named in.', 'lonsda-light-form'); ?></td></tr>
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

    <h3><?php esc_html_e('Two ways to run a form in several languages', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('Both work. Which suits you depends on whether the languages are saying the same thing.', 'lonsda-light-form'); ?>
    </p>

    <table class="widefat striped" style="max-width:900px;margin-bottom:16px;">
        <thead>
            <tr>
                <th style="width:50%;"><?php esc_html_e('One form, translated', 'lonsda-light-form'); ?></th>
                <th><?php esc_html_e('A separate form per language', 'lonsda-light-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Write the labels, placeholders and button in English. Translate them under Translations. Place the one form on every language version of the page.', 'lonsda-light-form'); ?></td>
                <td><?php esc_html_e('Build a form per language, each written in that language. Place each one on its own language version of the page.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Entries land together, with the language recorded on each. One list to read, filterable by language.', 'lonsda-light-form'); ?></td>
                <td><?php esc_html_e('Entries are separated by form, which is convenient if different people handle each language.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Adding a field means adding it once, then translating it.', 'lonsda-light-form'); ?></td>
                <td><?php esc_html_e('Adding a field means adding it to every form, and forgetting one is easy.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Notification recipients and wording are shared. One inbox, one template.', 'lonsda-light-form'); ?></td>
                <td><?php esc_html_e('Each language can notify different people, with its own subject and message.', 'lonsda-light-form'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Fields named the same way share a translation across forms, so "Email" is translated once for the whole site.', 'lonsda-light-form'); ?></td>
                <td><?php esc_html_e('No translation step at all — what you type is what is shown.', 'lonsda-light-form'); ?></td>
            </tr>
        </tbody>
    </table>

    <p style="max-width:860px;">
        <strong><?php esc_html_e('If you take the translated route, write the originals in English.', 'lonsda-light-form'); ?></strong>
        <?php esc_html_e('They are what a translator is shown and what a visitor sees when no translation exists for their language. Nothing enforces this and a form written in Latvian works — but changing your mind later means retyping every label, and any translation pointing at the old wording is orphaned when you do.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('If you take the separate-forms route, ignore Translations entirely. Nothing there applies, and a form whose labels are already in the right language needs no keys.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('The two can be mixed: a shared contact form translated once, and a campaign form written separately per language. Nothing links a form to a language, so the choice is per form.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('Where the keys come from', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('A key is worked out from the field name: a field named email gets field_email_label for its label and field_email_placeholder for its hint. Rename the field and both follow. There is nothing to fill in and nothing to keep in step — a key you cannot edit cannot drift from the field it names.', 'lonsda-light-form'); ?>
    </p>
    <table class="widefat striped" style="max-width:860px;margin-bottom:20px;">
        <thead>
            <tr>
                <th style="width:36%;"><?php esc_html_e('What is translated', 'lonsda-light-form'); ?></th>
                <th><?php esc_html_e('Key', 'lonsda-light-form'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr><td><?php esc_html_e('A field\'s label', 'lonsda-light-form'); ?></td><td><code>field_&lt;name&gt;_label</code></td></tr>
            <tr><td><?php esc_html_e('Its placeholder, when it has one', 'lonsda-light-form'); ?></td><td><code>field_&lt;name&gt;_placeholder</code></td></tr>
            <tr><td><?php esc_html_e('The submit button', 'lonsda-light-form'); ?></td><td><code>form_submit</code> — <?php esc_html_e('one key for every form, since "Send" is "Send" everywhere', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>
    <p style="max-width:860px;">
        <?php esc_html_e('Two forms with a field named the same way therefore share a translation, which is usually what you want — "Email" is "Email" everywhere. Give a field a distinct name where it should read differently.', 'lonsda-light-form'); ?>
    </p>

    <h3><?php esc_html_e('The form\'s own messages', 'lonsda-light-form'); ?></h3>
    <p style="max-width:860px;">
        <?php esc_html_e('The confirmation, the notification and the auto reply are the form speaking rather than asking, so they are keyed to the form rather than shared across the site. Two forms both saying "Thank you" may well want to say it differently, whereas two fields both labelled "Email" rarely do.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('The prefix is the form\'s Text ID, on the Fields tab. It is lower case with dashes for spaces — type anything else and it is converted on save, so the box always shows what the keys actually use. It is filled in from the title when the form is first saved and then left alone: changing it orphans any translation already made against the old one, which is why it does not follow the title the way a field key follows a field name.', 'lonsda-light-form'); ?>
    </p>
    <table class="widefat striped" style="max-width:860px;margin-bottom:20px;">
        <tbody>
            <tr><th style="width:44%;"><?php esc_html_e('Message after submission', 'lonsda-light-form'); ?></th><td><code>&lt;text-id&gt;__success_message</code></td></tr>
            <tr><th><?php esc_html_e('Notification subject and message', 'lonsda-light-form'); ?></th><td><code>__notification_subject</code>, <code>__notification_message</code></td></tr>
            <tr><th><?php esc_html_e('Auto reply subject and message', 'lonsda-light-form'); ?></th><td><code>__auto_reply_subject</code>, <code>__auto_reply_message</code></td></tr>
        </tbody>
    </table>
    <p style="max-width:860px;">
        <?php esc_html_e('Each is translated before its placeholders are filled in, so a translation can put {name} wherever that language wants it rather than where English happened to have it.', 'lonsda-light-form'); ?>
    </p>

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
    <p style="max-width:860px;">
        <?php esc_html_e('The list is grouped — form fields, submit button, confirmation, notification, auto reply — so it is clear which strings a visitor actually reads and which only you do.', 'lonsda-light-form'); ?>
    </p>
    <ul style="list-style:disc;padding-left:22px;max-width:860px;">
        <li><?php esc_html_e('An empty box means untranslated, and the original wording is shown instead. Nothing ever renders as a bare key.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Clearing a box that had a translation removes it.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('Saving one form does not disturb translations for another — only what is on screen is changed, and the rest of the file is kept.', 'lonsda-light-form'); ?></li>
        <li><?php esc_html_e('The language list comes from WPML or Polylang when one of them is running, since the translation plugin decides which locale a page is served as — and that is the only name a translation file can usefully have. Without either, WordPress\'s own installed translations are offered instead. The locale currently serving the page is marked, so a file that would never be looked for is easy to avoid naming.', 'lonsda-light-form'); ?></li>
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

    <h2><?php esc_html_e('Testing the emails', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php esc_html_e('The Testing tab on a form sends its notification or its auto reply to an address you choose — your own to begin with — with made-up answers filled in, so you can see how the message reads when it is full rather than empty.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('It sends through the same code a real submission does, so what arrives is what a visitor would cause: the same wording, placeholders, translations and Reply-To. Only the recipient is swapped, at the last moment. The subject is marked TEST, nothing is stored as an entry, and the IP and browser are left blank rather than recording yours against a message that was never really sent.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('Both buttons read what was last saved, so save the form before testing a change. A button is disabled when its message is not configured, and says what would switch it on.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Auto reply', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php esc_html_e('A form can email the person who submitted it, confirming that the message arrived. Switch it on per form on the Auto reply tab and edit the wording there — it ships with something sensible that promises nothing the site cannot keep.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('It is off unless you turn it on. This mails an address a stranger typed into a public form, which is how a form becomes a way of sending mail to somebody who never asked for it — worth switching on deliberately rather than discovering later.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('The address comes only from a field with email validation. A field merely named something like one has never been checked, and guessing would mean mailing whatever was typed into it. Without such a field nothing is sent, and the reason is logged.', 'lonsda-light-form'); ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('It takes the same placeholders as a notification and goes out as both an HTML message and a plain-text one, so a mail client that shows text gets the paragraphs and not one unbroken block. Keep it short, and leave out anything the visitor did not tell you — their IP address has no business in a message addressed to them.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Styling a rejected submission', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php esc_html_e('When a submission is rejected the form comes back with the answers still in it and the offending fields marked, so a theme can colour them with CSS alone.', 'lonsda-light-form'); ?>
    </p>
    <table class="widefat striped" style="max-width:860px;margin-bottom:12px;">
        <tbody>
            <tr><th style="width:230px;"><code>llf-form--has-errors</code></th><td><?php esc_html_e('On the form, when anything was rejected.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><code>llf-field--error</code></th><td><?php esc_html_e('On the wrapper of each rejected field.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><code>llf-input--error</code></th><td><?php esc_html_e('On the input itself — usually the one you want.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><code>llf-error</code></th><td><?php esc_html_e('On the message beneath a rejected field.', 'lonsda-light-form'); ?></td></tr>
            <tr><th><code>llf-notice--error</code></th><td><?php esc_html_e('On the message above the form.', 'lonsda-light-form'); ?></td></tr>
        </tbody>
    </table>
    <pre style="background:#f6f7f7;padding:12px;max-width:860px;overflow:auto;"><code>.llf-input--error { border-color: #d63638; }
.llf-field--error .llf-error { color: #d63638; }</code></pre>
    <p style="max-width:860px;">
        <?php esc_html_e('Only rejected fields are marked — a class everything wears is no more use than one nothing wears. Rejected inputs also carry aria-invalid and point at their message, so the reason is announced and not only coloured.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Spam', 'lonsda-light-form'); ?></h2>
    <p>
        <?php esc_html_e('Every form carries a hidden honeypot field and records when it was opened; a submission that fills the honeypot in, or arrives faster than the configured minimum, is refused. Both are configurable under Settings, and neither tells the sender which check it tripped — that would only help the next attempt.', 'lonsda-light-form'); ?>
    </p>

    <h2><?php esc_html_e('Moving forms between sites', 'lonsda-light-form'); ?></h2>
    <p style="max-width:860px;">
        <?php
        printf(
            /* translators: %s: link to the settings page */
            wp_kses(
                __('The Import / Export tab under <a href="%s">Settings</a> downloads forms as JSON — all of them, or whichever you tick — and takes a file back. What travels is the form itself: its fields, wording and notification settings. Entries do not, because they are a record of what people sent rather than part of the form.', 'lonsda-light-form'),
                ['a' => ['href' => []]]
            ),
            esc_url($llf_settings_url)
        );
        ?>
    </p>
    <p style="max-width:860px;">
        <?php esc_html_e('Importing always adds. It never merges into a form you already have — two forms can share a title, and replacing the wrong one is worse than leaving a duplicate to delete. A file that was not exported from this plugin is refused rather than half-read.', 'lonsda-light-form'); ?>
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
