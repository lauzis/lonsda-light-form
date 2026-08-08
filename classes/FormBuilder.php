<?php

namespace LonsdaLightForm;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * The form structure editor.
 *
 * Carbon Fields supplies the repeatable field list; this class decides what a
 * field can be and reads the result back out as a plain array, which Forms
 * stores as JSON.
 */
class FormBuilder
{
    /** Input types a field can be. */
    public const TYPES = [
        'text'     => 'Text',
        'textarea' => 'Text area',
        'checkbox' => 'Checkbox',
    ];

    /** Validation a field can carry. Deliberately small to start with. */
    public const VALIDATIONS = [
        ''      => 'None',
        'email' => 'Email address',
        'regex' => 'Custom pattern',
    ];

    public static function init(): void
    {
        add_action('carbon_fields_register_fields', [self::class, 'register']);
    }

    public static function register(): void
    {
        if (!class_exists('\Carbon_Fields\Container')) {
            return;
        }

        $fieldsTab = [
            // Form-level identity, above the fields because everything else
            // about the form is named after it.
            Field::make('text', 'llf_text_id', __('Text ID', 'lonsda-light-form'))
                ->set_help_text(__('Names this form\'s own messages for translation — the confirmation, the notification and the auto reply all key off it. Filled in from the title when the form is first saved and then left alone: changing it orphans any translation already made against the old one. Lower case, dashes for spaces; anything else you type is converted to that on save.', 'lonsda-light-form')),

            Field::make('complex', 'llf_fields', __('Fields', 'lonsda-light-form'))
                ->set_help_text(__('The inputs this form asks for, in the order they appear.', 'lonsda-light-form'))
                ->add_fields([
                    Field::make('text', 'label', __('Label', 'lonsda-light-form'))
                        ->set_required(true)
                        ->set_help_text(__('Shown above the input.', 'lonsda-light-form')),

                    Field::make('text', 'name', __('Name', 'lonsda-light-form'))
                        ->set_help_text(__('Identifier used when the submission is stored or emailed. Leave blank to derive it from the label.', 'lonsda-light-form')),

                    Field::make('select', 'type', __('Type', 'lonsda-light-form'))
                        ->set_options([
                            'text'     => __('Text', 'lonsda-light-form'),
                            'textarea' => __('Text area', 'lonsda-light-form'),
                            'checkbox' => __('Checkbox', 'lonsda-light-form'),
                        ])
                        ->set_default_value('text')
                        ->set_help_text(__('A single-line input, a multi-line box, or a single tick box.', 'lonsda-light-form')),

                    Field::make('text', 'placeholder', __('Placeholder', 'lonsda-light-form'))
                        ->set_help_text(__('Hint shown inside the empty input. Not a substitute for the label, which stays visible.', 'lonsda-light-form'))
                        ->set_conditional_logic([
                            ['field' => 'type', 'value' => 'checkbox', 'compare' => '!='],
                        ]),

                    Field::make('checkbox', 'default_checked', __('Ticked by default', 'lonsda-light-form'))
                        ->set_help_text(__('Whether the box starts ticked. Leave off for anything the visitor must actively agree to.', 'lonsda-light-form'))
                        ->set_conditional_logic([
                            ['field' => 'type', 'value' => 'checkbox', 'compare' => '='],
                        ]),

                    Field::make('checkbox', 'required', __('Required', 'lonsda-light-form'))
                        ->set_help_text(__('The submission is rejected when this is left empty, or unticked for a checkbox.', 'lonsda-light-form')),

                    Field::make('select', 'validation', __('Validation', 'lonsda-light-form'))
                        ->set_options(self::VALIDATIONS)
                        ->set_default_value('')
                        // Email validation on a multi-line box makes no sense,
                        // so it is only offered for single-line inputs.
                        ->set_conditional_logic([
                            ['field' => 'type', 'value' => 'text', 'compare' => '='],
                        ]),

                    Field::make('text', 'pattern', __('Pattern', 'lonsda-light-form'))
                        ->set_help_text(__('A regular expression the value must match, without delimiters — for example [A-Z]{2}[0-9]{4}', 'lonsda-light-form'))
                        ->set_conditional_logic([
                            ['field' => 'validation', 'value' => 'regex', 'compare' => '='],
                        ]),

                    Field::make('text', 'max_length', __('Maximum length', 'lonsda-light-form'))
                        ->set_attribute('type', 'number')
                        ->set_attribute('min', '0')
                        ->set_help_text(__('Longest accepted value, in characters. Leave blank for no limit.', 'lonsda-light-form')),
                ])
                // Collapsed, so a form with a dozen fields opens as a list of
                // labels rather than a wall of inputs. The header template is
                // what makes that readable.
                ->set_collapsed(true)
                ->set_header_template('<%- label || "Field" %>'),
        ];

        // Offered only when reCAPTCHA is actually configured. An option that
        // cannot work is worse than an absent one — it invites someone to turn
        // it on and assume they are protected.
        $protectionTab = self::recaptchaConfigured()
            ? [
                Field::make('checkbox', 'llf_recaptcha', __('Protect with reCAPTCHA', 'lonsda-light-form'))
                    ->set_help_text(__('Adds a Google reCAPTCHA v2 challenge to this form, using the keys from Settings.', 'lonsda-light-form')),
            ]
            : [
                Field::make('html', 'llf_recaptcha_note')
                    ->set_html(
                        '<p class="description">' . sprintf(
                            /* translators: %s: link to the settings page */
                            esc_html__('reCAPTCHA can be switched on per form once both keys are filled in under %s.', 'lonsda-light-form'),
                            '<a href="' . esc_url(admin_url('admin.php?page=' . LLF_SLUG . '-settings')) . '">' . esc_html__('Settings', 'lonsda-light-form') . '</a>'
                        ) . '</p>'
                    ),
            ];

        $buttonTab = [
            Field::make('text', 'llf_submit_label', __('Submit button text', 'lonsda-light-form'))
                ->set_default_value(self::defaultSubmitLabel())
                ->set_help_text(__('Wording on the button. Leave empty for the default.', 'lonsda-light-form')),
        ];

        $confirmationTab = [
            Field::make('rich_text', 'llf_success_message', __('Message after submission', 'lonsda-light-form'))
                ->set_settings(['media_buttons' => false])
                ->set_default_value(self::defaultSuccessMessage())
                ->set_help_text(__('Shown once the form has been accepted. Leave empty to use the default wording.', 'lonsda-light-form')),

            // Default on: leaving a filled-in form on screen under a "thank you"
            // reads as though nothing was sent, and invites a second submission.
            Field::make('checkbox', 'llf_hide_on_success', __('Hide the form after submission', 'lonsda-light-form'))
                ->set_default_value(true)
                ->set_help_text(__('Replaces the form with the message above. Switch off to leave the form in place so another submission can be made.', 'lonsda-light-form')),
        ];

        // Tabbed rather than one long column: the field list is what gets
        // edited repeatedly, and everything else was pushing it off the screen.
        // Every field is assigned to a tab — Carbon Fields collects any that
        // are not into a "General" tab of its own, which would look accidental.
        $notificationsTab = [
            // Prefilled rather than assumed at send time: the address is in the
            // box where it can be read and changed before the form is saved,
            // instead of a form quietly mailing an address nobody chose.
            Field::make('text', 'llf_notify_to', __('Send notifications to', 'lonsda-light-form'))
                ->set_default_value(self::defaultNotifyTo())
                ->set_help_text(
                    sprintf(
                        /* translators: %s: the site's administration email address */
                        __('Email addresses, separated by commas. Filled in with the site administration address (%s) — change it or clear it to send nothing.', 'lonsda-light-form'),
                        self::defaultNotifyTo()
                    )
                ),

            // {form_title} rather than the title itself: a default is fixed
            // when the field is registered and has no post to ask, and this way
            // the subject keeps up with a form that is later renamed.
            Field::make('text', 'llf_notify_subject', __('Subject', 'lonsda-light-form'))
                ->set_default_value(self::defaultNotifySubject())
                ->set_help_text(__('{form_title} and {site_name} are replaced when the mail is sent. Leave empty for "New submission: <form title>".', 'lonsda-light-form')),

            Field::make('textarea', 'llf_notify_message', __('Message', 'lonsda-light-form'))
                ->set_rows(8)
                ->set_help_text(__('Leave empty to list every field and its answer. Otherwise write your own: {field_name} is replaced by that field\'s answer, and {all_fields} by the whole list. See Help for everything available.', 'lonsda-light-form')),

            Field::make('text', 'llf_notify_reply_to', __('Reply-To field', 'lonsda-light-form'))
                ->set_help_text(__('The Name of a field collecting an email address — the notification then replies to whoever submitted it. Leave empty for none.', 'lonsda-light-form')),

            Field::make('separator', 'llf_entries_separator', __('Entries', 'lonsda-light-form')),

            Field::make('checkbox', 'llf_store_entries', __('Keep submissions in the database', 'lonsda-light-form'))
                ->set_default_value(true)
                ->set_help_text(__('Listed under Lonsda Forms → Entries. Storing them means a notification that never arrives is not a submission lost.', 'lonsda-light-form')),
        ];

        $autoReplyTab = [
            // Off by default. It emails whoever typed an address into a public
            // form, so it should be switched on deliberately rather than found
            // to have been running.
            Field::make('checkbox', 'llf_auto_reply', __('Send an auto reply', 'lonsda-light-form'))
                ->set_help_text(__('Emails the person who submitted the form, to the address they gave. Needs a field with email validation — without one there is nowhere to send it.', 'lonsda-light-form')),

            Field::make('text', 'llf_auto_reply_subject', __('Subject', 'lonsda-light-form'))
                ->set_default_value(self::defaultAutoReplySubject())
                ->set_conditional_logic([
                    ['field' => 'llf_auto_reply', 'value' => true],
                ]),

            Field::make('rich_text', 'llf_auto_reply_message', __('Message', 'lonsda-light-form'))
                ->set_settings(['media_buttons' => false])
                ->set_default_value(self::defaultAutoReplyMessage())
                ->set_help_text(__('The same placeholders as a notification: {field_name} for an answer, {form_title}, {site_name} and the rest. Written to the visitor, so keep it short and do not include their IP address or anything else they did not tell you.', 'lonsda-light-form'))
                ->set_conditional_logic([
                    ['field' => 'llf_auto_reply', 'value' => true],
                ]),
        ];

        Container::make('post_meta', __('Form Structure', 'lonsda-light-form'))
            ->where('post_type', '=', Forms::POST_TYPE)
            ->add_tab(__('Fields', 'lonsda-light-form'), $fieldsTab)
            ->add_tab(__('Submit button', 'lonsda-light-form'), $buttonTab)
            ->add_tab(__('Confirmation', 'lonsda-light-form'), $confirmationTab)
            ->add_tab(__('Notifications', 'lonsda-light-form'), $notificationsTab)
            ->add_tab(__('Auto reply', 'lonsda-light-form'), $autoReplyTab)
            ->add_tab(__('Protection', 'lonsda-light-form'), $protectionTab);
    }

    /** A trimmed string from post meta, or '' when Carbon Fields is absent. */
    private static function meta(int $post_id, string $key): string
    {
        return function_exists('carbon_get_post_meta')
            ? trim((string) carbon_get_post_meta($post_id, $key))
            : '';
    }

    /**
     * Address a new form's notifications are prefilled with.
     *
     * The site administration address from Settings → General, which is the
     * only contact address WordPress itself keeps.
     */
    public static function defaultNotifyTo(): string
    {
        return (string) get_option('admin_email', '');
    }

    /** Subject a new form's notifications are prefilled with. */
    public static function defaultNotifySubject(): string
    {
        return '{form_title}';
    }

    /** Wording on the submit button when a form does not set its own. */
    public static function defaultSubmitLabel(): string
    {
        return __('Send', 'lonsda-light-form');
    }

    /** The key a field's label is translated by. */
    public static function generatedFieldKey(string $name): string
    {
        return 'field_' . $name . '_label';
    }

    /**
     * This form's own identifier, for keying the messages that belong to it.
     *
     * Generated once from the title and then stored, rather than derived from
     * it every time. A field's key can follow its name because renaming a field
     * is a small thing; a form is renamed for presentation, and having every
     * message translation vanish because somebody tidied up a title would be a
     * poor trade. Falls back to the post id, which is never empty.
     */
    public static function textId(int $post_id): string
    {
        $raw   = self::meta($post_id, 'llf_text_id');
        $clean = sanitize_title($raw);

        if ('' !== $clean) {
            // Written back when typing it produced something different —
            // lower case, spaces to dashes, accents folded. The box otherwise
            // shows "My Form ID" while every key says my-form-id, and the one
            // place you would go to check which is in use is the one place
            // telling you the wrong answer.
            if ($clean !== $raw && function_exists('carbon_set_post_meta')) {
                carbon_set_post_meta($post_id, 'llf_text_id', $clean);
            }

            return $clean;
        }

        $slug = sanitize_title((string) get_post_field('post_name', $post_id));

        if ('' === $slug) {
            $slug = sanitize_title((string) get_the_title($post_id));
        }

        $slug = $slug ?: 'form-' . $post_id;

        // Written back so it is visible and stable from here on, rather than
        // being recomputed — and changing later — behind the scenes.
        if (function_exists('carbon_set_post_meta')) {
            carbon_set_post_meta($post_id, 'llf_text_id', $slug);
        }

        return $slug;
    }

    /**
     * The key one of a form's own messages is translated by.
     *
     * Prefixed with the form's text id because these are the form's words, not
     * a field's: two forms both saying "Thank you" may well want to say it
     * differently, whereas two fields both labelled "Email" rarely do.
     */
    public static function formStringKey(string $textId, string $part): string
    {
        return $textId . '__' . $part;
    }

    /** The key a field's placeholder is translated by. */
    public static function generatedPlaceholderKey(string $name): string
    {
        return 'field_' . $name . '_placeholder';
    }

    /**
     * The key the submit button is translated by.
     *
     * One key for every form on the site, not one per form. A button reading
     * "Send" is "Send" everywhere, and a key per form would mean translating
     * the same word again for each — which is exactly the busywork this is
     * supposed to remove. A form that genuinely needs different wording can
     * still have it: the button text is a field, and only the translation is
     * shared.
     */
    public static function generatedSubmitKey(): string
    {
        return 'form_submit';
    }

    /** Subject a new form's auto reply is prefilled with. */
    public static function defaultAutoReplySubject(): string
    {
        return __('We have received your message — {site_name}', 'lonsda-light-form');
    }

    /**
     * The auto reply as shipped.
     *
     * Says only what is true of every enquiry: it arrived, and somebody will
     * read it. Promising a timescale the site cannot keep is worse than
     * promising nothing.
     */
    public static function defaultAutoReplyMessage(): string
    {
        return '<p>' . __('Thank you for getting in touch.', 'lonsda-light-form') . '</p>'
            . '<p>' . __('Your message has reached us and we will read it. If it needs an answer, we will reply as soon as we can — please bear with us.', 'lonsda-light-form') . '</p>'
            . '<p>' . __('There is no need to send it again.', 'lonsda-light-form') . '</p>';
    }

    /** Wording used when a form does not set its own. */
    public static function defaultSuccessMessage(): string
    {
        return '<p>' . __('Thank you — your message has been sent.', 'lonsda-light-form') . '</p>';
    }

    /**
     * Whether the field definitions can be read at all.
     *
     * carbon_get_post_meta() exists as soon as the library is loaded, but
     * answers with nothing until the fields have been registered — so a read
     * before carbon_fields_register_fields has fired looks like a form with no
     * fields rather than like a question that cannot yet be answered. Anything
     * that writes a definition has to check this first.
     */
    public static function ready(): bool
    {
        return function_exists('carbon_get_post_meta')
            && function_exists('did_action')
            && did_action('carbon_fields_register_fields') > 0;
    }

    /**
     * Whether a form should actually show a challenge.
     *
     * Both halves have to hold: the form asked for it, and the site can provide
     * it. Asked at render and again at validation rather than stored, so
     * removing the keys disables it everywhere at once.
     *
     * @param array $settings A stored form definition.
     */
    public static function recaptchaActive(array $settings): bool
    {
        return !empty($settings['recaptcha']) && self::recaptchaConfigured();
    }

    /** True when both reCAPTCHA keys are present. */
    public static function recaptchaConfigured(): bool
    {
        return '' !== trim((string) Settings::get('recaptcha_site_key', ''))
            && '' !== trim((string) Settings::get('recaptcha_secret_key', ''));
    }

    /**
     * Reads a form's structure back out as a plain array, ready to store.
     *
     * Normalised here rather than at render time so whatever reads the table
     * gets a predictable shape: every field has every key, names are filled in,
     * and a max length of zero means "no limit" rather than "reject everything".
     *
     * @return array{fields: array[], recaptcha: bool}
     */
    public static function definition(int $post_id): array
    {
        $rows   = function_exists('carbon_get_post_meta') ? carbon_get_post_meta($post_id, 'llf_fields') : [];
        $fields = [];
        $used   = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));

            if ('' === $label) {
                continue;
            }

            $name = sanitize_key((string) ($row['name'] ?? ''));

            if ('' === $name) {
                $name = sanitize_key($label);
            }

            // Two fields sharing a name would silently overwrite each other in
            // the submission, so later duplicates are suffixed.
            $base = $name ?: 'field';
            $n    = 2;
            while (in_array($name, $used, true) || '' === $name) {
                $name = $base . '_' . $n;
                $n++;
            }
            $used[] = $name;

            $max  = (int) ($row['max_length'] ?? 0);
            $type = (string) ($row['type'] ?? 'text');
            $type = array_key_exists($type, self::TYPES) ? $type : 'text';

            $validation = (string) ($row['validation'] ?? '');
            $validation = array_key_exists($validation, self::VALIDATIONS) ? $validation : '';

            // The editor hides email validation for a text area; enforced here
            // too, so a value left behind by a type change cannot survive.
            if ('textarea' === $type && 'email' === $validation) {
                $validation = '';
            }

            $checkbox = 'checkbox' === $type;

            $fields[] = [
                'label'           => $label,
                // Derived from the name every time rather than stored and
                // edited. A key nobody can change cannot drift from the field
                // it names, and the editor is one box shorter for it.
                'translation_key' => self::generatedFieldKey($name),
                'placeholder_key' => self::generatedPlaceholderKey($name),
                'name'            => $name,
                'type'        => $type,
                // A checkbox has nothing to put a placeholder in, and validation
                // beyond "must be ticked" has no meaning for one.
                'placeholder' => $checkbox ? '' : (string) ($row['placeholder'] ?? ''),
                'required'    => (bool) ($row['required'] ?? false),
                'checked'     => $checkbox && (bool) ($row['default_checked'] ?? false),
                'validation'  => $checkbox ? '' : $validation,
                'pattern'     => $checkbox ? '' : (string) ($row['pattern'] ?? ''),
                'max_length'  => (!$checkbox && $max > 0) ? $max : null,
            ];
        }

        $recaptcha = function_exists('carbon_get_post_meta')
            ? (bool) carbon_get_post_meta($post_id, 'llf_recaptcha')
            : false;

        $submit = function_exists('carbon_get_post_meta')
            ? trim((string) carbon_get_post_meta($post_id, 'llf_submit_label'))
            : '';

        $textId    = self::textId($post_id);
        $submitKey = self::generatedSubmitKey();

        $success = function_exists('carbon_get_post_meta')
            ? trim((string) carbon_get_post_meta($post_id, 'llf_success_message'))
            : '';

        // Carbon Fields falls back to a field's default only when nothing is
        // stored, and an unticked box stores an empty string rather than
        // nothing — so this reads true for a form saved before the setting
        // existed, and false once someone actually switches it off.
        $hide = function_exists('carbon_get_post_meta')
            ? (bool) carbon_get_post_meta($post_id, 'llf_hide_on_success')
            : true;

        return [
            'fields'    => $fields,
            // The form's own intent, not whether it can currently run. Whether
            // the keys exist is a site setting that changes independently, and
            // baking it in here left a form that had reCAPTCHA switched on
            // recorded as not using it until someone happened to re-save it.
            // recaptchaActive() applies the site half, at the moment it matters.
            'recaptcha'       => $recaptcha,
            'success_message' => $success,
            'hide_on_success' => $hide,
            'text_id'         => $textId,
            'submit_label'    => $submit,
            'submit_key'      => $submitKey,
            // Keys for the form's own wording, so whatever renders or sends it
            // does not have to know how they are put together.
            'success_key'          => self::formStringKey($textId, 'success_message'),
            'notify_subject_key'   => self::formStringKey($textId, 'notification_subject'),
            'notify_message_key'   => self::formStringKey($textId, 'notification_message'),
            'auto_reply_subject_key' => self::formStringKey($textId, 'auto_reply_subject'),
            'auto_reply_message_key' => self::formStringKey($textId, 'auto_reply_message'),
            'notify_to'       => self::meta($post_id, 'llf_notify_to'),
            'notify_subject'  => self::meta($post_id, 'llf_notify_subject'),
            'notify_message'  => self::meta($post_id, 'llf_notify_message'),
            'notify_reply_to' => self::meta($post_id, 'llf_notify_reply_to'),
            'auto_reply'      => function_exists('carbon_get_post_meta')
                ? (bool) carbon_get_post_meta($post_id, 'llf_auto_reply')
                : false,
            'auto_reply_subject' => self::meta($post_id, 'llf_auto_reply_subject'),
            'auto_reply_message' => self::meta($post_id, 'llf_auto_reply_message'),
            'store_entries'   => function_exists('carbon_get_post_meta')
                ? (bool) carbon_get_post_meta($post_id, 'llf_store_entries')
                : true,
        ];
    }
}
