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

                    Field::make('text', 'translation_key', __('Translation key', 'lonsda-light-form'))
                        ->set_help_text(__('Identifies this label for translation. Filled in from the name and kept in step with it — until you change it, after which it is left alone. Clear it to go back to the generated one.', 'lonsda-light-form')),

                    // Remembers what was last generated, which is the only way
                    // to tell "the name changed, so regenerate" apart from
                    // "someone chose this key, so leave it alone".
                    Field::make('hidden', 'translation_key_auto'),
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

            Field::make('text', 'llf_submit_translation_key', __('Translation key', 'lonsda-light-form'))
                ->set_help_text(__('Filled in from the form title and kept in step with it, unless you change it. Clear it to go back to the generated one.', 'lonsda-light-form')),

            Field::make('hidden', 'llf_submit_translation_key_auto'),
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
            Field::make('text', 'llf_notify_to', __('Send notifications to', 'lonsda-light-form'))
                ->set_help_text(__('Email addresses, separated by commas. Leave empty to send nothing — no address is assumed, so a new form does not start mailing anyone by itself.', 'lonsda-light-form')),

            Field::make('text', 'llf_notify_subject', __('Subject', 'lonsda-light-form'))
                ->set_help_text(__('Leave empty for "New submission: <form title>". {form_title} and {site_name} are replaced.', 'lonsda-light-form')),

            Field::make('text', 'llf_notify_reply_to', __('Reply-To field', 'lonsda-light-form'))
                ->set_help_text(__('The Name of a field collecting an email address — the notification then replies to whoever submitted it. Leave empty for none.', 'lonsda-light-form')),

            Field::make('separator', 'llf_entries_separator', __('Entries', 'lonsda-light-form')),

            Field::make('checkbox', 'llf_store_entries', __('Keep submissions in the database', 'lonsda-light-form'))
                ->set_default_value(true)
                ->set_help_text(__('Listed under Lonsda Forms → Entries. Storing them means a notification that never arrives is not a submission lost.', 'lonsda-light-form')),
        ];

        Container::make('post_meta', __('Form Structure', 'lonsda-light-form'))
            ->where('post_type', '=', Forms::POST_TYPE)
            ->add_tab(__('Fields', 'lonsda-light-form'), $fieldsTab)
            ->add_tab(__('Submit button', 'lonsda-light-form'), $buttonTab)
            ->add_tab(__('Confirmation', 'lonsda-light-form'), $confirmationTab)
            ->add_tab(__('Notifications', 'lonsda-light-form'), $notificationsTab)
            ->add_tab(__('Protection', 'lonsda-light-form'), $protectionTab);
    }

    /**
     * Stores the resolved keys back on the post, so the editor shows them.
     *
     * Without this the boxes stay as they were typed — empty for a new field —
     * and the key a form is actually using would only be visible by reading the
     * table. Only the key columns are touched; the rest of each row is left as
     * the editor saved it.
     *
     * @param array<int, array{translation_key: string, translation_key_auto: string}> $keys
     */
    private static function writeBackKeys(int $post_id, array $keys, string $submitKey, string $submitKeyAuto): void
    {
        if (!function_exists('carbon_get_post_meta') || !function_exists('carbon_set_post_meta')) {
            return;
        }

        $rows    = carbon_get_post_meta($post_id, 'llf_fields');
        $rows    = is_array($rows) ? $rows : [];
        $changed = false;

        // $keys is built in the same pass that builds the definition, which
        // skips rows with no label — so it is indexed independently of $rows.
        $i = 0;

        foreach ($rows as $index => $row) {
            if ('' === trim((string) ($row['label'] ?? ''))) {
                continue;
            }

            if (!isset($keys[$i])) {
                break;
            }

            foreach ($keys[$i] as $column => $value) {
                if ((string) ($row[$column] ?? '') !== $value) {
                    $rows[$index][$column] = $value;
                    $changed               = true;
                }
            }

            $i++;
        }

        if ($changed) {
            carbon_set_post_meta($post_id, 'llf_fields', $rows);
        }

        if ((string) carbon_get_post_meta($post_id, 'llf_submit_translation_key') !== $submitKey) {
            carbon_set_post_meta($post_id, 'llf_submit_translation_key', $submitKey);
        }

        if ((string) carbon_get_post_meta($post_id, 'llf_submit_translation_key_auto') !== $submitKeyAuto) {
            carbon_set_post_meta($post_id, 'llf_submit_translation_key_auto', $submitKeyAuto);
        }
    }

    /** A trimmed string from post meta, or '' when Carbon Fields is absent. */
    private static function meta(int $post_id, string $key): string
    {
        return function_exists('carbon_get_post_meta')
            ? trim((string) carbon_get_post_meta($post_id, $key))
            : '';
    }

    /** Wording on the submit button when a form does not set its own. */
    public static function defaultSubmitLabel(): string
    {
        return __('Send', 'lonsda-light-form');
    }

    /** The key a field's label gets when nobody has chosen one. */
    public static function generatedFieldKey(string $name): string
    {
        return 'field_' . $name . '_label';
    }

    /** The key a form's submit button gets when nobody has chosen one. */
    public static function generatedSubmitKey(string $slug): string
    {
        return 'form_' . ($slug ?: 'form') . '_submit';
    }

    /**
     * Decides whether a translation key follows its source or stands on its own.
     *
     * A key that still matches what was last generated is one nobody has
     * touched, so it follows the name and is regenerated. A key that differs
     * was chosen deliberately and is kept, even when the name later changes —
     * which is the whole point of recording what was generated. Clearing the
     * field puts it back under automatic control.
     *
     * @return array{0: string, 1: string} The key to use, and the generated
     *                                     value to remember for next time.
     */
    public static function resolveTranslationKey(string $current, string $lastGenerated, string $expected): array
    {
        $current = trim($current);

        if ('' === $current || $current === trim($lastGenerated)) {
            return [$expected, $expected];
        }

        // Deliberately keeps the old generated value: it is the record of what
        // this key was compared against, and overwriting it would make a
        // customised key look untouched the next time the name changes.
        return [$current, trim($lastGenerated)];
    }

    /** Wording used when a form does not set its own. */
    public static function defaultSuccessMessage(): string
    {
        return '<p>' . __('Thank you — your message has been sent.', 'lonsda-light-form') . '</p>';
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
        $rows      = function_exists('carbon_get_post_meta') ? carbon_get_post_meta($post_id, 'llf_fields') : [];
        $fields    = [];
        $used      = [];
        $writeBack = [];

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

            [$key, $keyAuto] = self::resolveTranslationKey(
                (string) ($row['translation_key'] ?? ''),
                (string) ($row['translation_key_auto'] ?? ''),
                self::generatedFieldKey($name)
            );

            // Written back so the editor shows the key the form is actually
            // using, rather than leaving the box empty and the person guessing.
            $writeBack[] = ['translation_key' => $key, 'translation_key_auto' => $keyAuto];

            $fields[] = [
                'label'           => $label,
                'translation_key' => $key,
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

        $slug   = sanitize_key(get_post_field('post_name', $post_id) ?: (string) $post_id);
        $submit = function_exists('carbon_get_post_meta')
            ? trim((string) carbon_get_post_meta($post_id, 'llf_submit_label'))
            : '';

        [$submitKey, $submitKeyAuto] = self::resolveTranslationKey(
            function_exists('carbon_get_post_meta') ? (string) carbon_get_post_meta($post_id, 'llf_submit_translation_key') : '',
            function_exists('carbon_get_post_meta') ? (string) carbon_get_post_meta($post_id, 'llf_submit_translation_key_auto') : '',
            self::generatedSubmitKey($slug)
        );

        self::writeBackKeys($post_id, $writeBack, $submitKey, $submitKeyAuto);

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
            // Recorded as off unless it can actually run, so a form cannot claim
            // protection the site is not configured to provide.
            'recaptcha'       => $recaptcha && self::recaptchaConfigured(),
            'success_message' => $success,
            'hide_on_success' => $hide,
            'submit_label'    => $submit,
            'submit_key'      => $submitKey,
            'notify_to'       => self::meta($post_id, 'llf_notify_to'),
            'notify_subject'  => self::meta($post_id, 'llf_notify_subject'),
            'notify_reply_to' => self::meta($post_id, 'llf_notify_reply_to'),
            'store_entries'   => function_exists('carbon_get_post_meta')
                ? (bool) carbon_get_post_meta($post_id, 'llf_store_entries')
                : true,
        ];
    }
}
