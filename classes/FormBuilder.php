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

        $fields = [
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
                ->set_header_template('<%- label || "Field" %>'),
        ];

        // Offered only when reCAPTCHA is actually configured. An option that
        // cannot work is worse than an absent one — it invites someone to turn
        // it on and assume they are protected.
        if (self::recaptchaConfigured()) {
            $fields[] = Field::make('checkbox', 'llf_recaptcha', __('Protect with reCAPTCHA', 'lonsda-light-form'))
                ->set_help_text(__('Adds a Google reCAPTCHA v2 challenge to this form, using the keys from Settings.', 'lonsda-light-form'));
        } else {
            $fields[] = Field::make('html', 'llf_recaptcha_note')
                ->set_html(
                    '<p class="description">' . sprintf(
                        /* translators: %s: link to the settings page */
                        esc_html__('reCAPTCHA can be switched on per form once both keys are filled in under %s.', 'lonsda-light-form'),
                        '<a href="' . esc_url(admin_url('admin.php?page=' . LLF_SLUG . '-settings')) . '">' . esc_html__('Settings', 'lonsda-light-form') . '</a>'
                    ) . '</p>'
                );
        }

        Container::make('post_meta', __('Form Structure', 'lonsda-light-form'))
            ->where('post_type', '=', Forms::POST_TYPE)
            ->add_fields($fields);
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
                'label'       => $label,
                'name'        => $name,
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

        return [
            'fields'    => $fields,
            // Recorded as off unless it can actually run, so a form cannot claim
            // protection the site is not configured to provide.
            'recaptcha' => $recaptcha && self::recaptchaConfigured(),
        ];
    }
}
