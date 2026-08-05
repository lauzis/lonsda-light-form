<?php

namespace LonsdaLightForm;

/**
 * Renders a stored form definition as HTML.
 *
 * Reads the custom table, never the post meta: the table is the runtime record,
 * so the front end does one indexed lookup rather than a post plus meta queries.
 */
class Renderer
{
    /** Query var carrying the id of the form being submitted. */
    public const FIELD_FORM_ID = 'llf_form_id';

    /** Honeypot input name — plausible enough that a bot fills it in. */
    public const FIELD_HONEYPOT = 'llf_website';

    /** Hidden timestamp, for the minimum-completion-time check. */
    public const FIELD_STARTED = 'llf_started';

    /**
     * @param int   $id   Form id, as stored in the table.
     * @param array $args {
     *     @type array  $values  Values to re-populate with, after a failed submit.
     *     @type array  $errors  Field name => message.
     *     @type string $notice  Message shown above the form.
     *     @type bool   $success Whether the submission was accepted.
     * }
     */
    public static function form(int $id, array $args = []): string
    {
        $form = Forms::get($id);

        if (!$form) {
            // A form has two identifiers — the post it is edited as, and its
            // row in this table — and the id given is far more likely to be the
            // wrong one than to be invented. Worth resolving, because "no form
            // with id 45606" gives whoever reads it nothing to act on.
            $actual = Forms::tableIdForPost($id);

            Logs::error('render', 'A form was requested that does not exist.', [
                'id'              => $id,
                'is_a_form_post'  => $actual > 0,
                'correct_id'      => $actual ?: null,
            ]);

            if (!current_user_can('manage_options')) {
                // Deliberately quiet on the front end: a mistyped id should not
                // print an error into a visitor's page.
                return '';
            }

            $message = $actual > 0
                ? sprintf(
                    /* translators: 1: id that was used, 2: id that should be used */
                    __('Lonsda: %1$d is the post id of a form, not its form id. Use %2$d instead — the id shown in the Forms list.', 'lonsda-light-form'),
                    $id,
                    $actual
                )
                : sprintf(
                    /* translators: %d: form id */
                    __('Lonsda: no form with id %d. Check the id in the Forms list.', 'lonsda-light-form'),
                    $id
                );

            return '<p><em>' . esc_html($message) . '</em></p>';
        }

        $fields = $form['settings']['fields'] ?? [];

        $success         = !empty($args['success']);
        $success_message = '';

        if ($success) {
            $success_message = trim((string) ($form['settings']['success_message'] ?? ''));

            if ('' === $success_message) {
                $success_message = FormBuilder::defaultSuccessMessage();
            }

            $success_message = Strings::get(
                $success_message,
                (string) ($form['settings']['success_key'] ?? '')
            );

            // Absent means a form saved before the setting existed, which is
            // the same case the field's default covers: hide it.
            $hide = !array_key_exists('hide_on_success', $form['settings'])
                || !empty($form['settings']['hide_on_success']);

            if ($hide) {
                // Returned without the form, and without the empty-fields check
                // below: whether the form still has fields has no bearing on
                // confirming a submission that already happened.
                return '<div class="llf-form llf-form--sent">'
                    . '<div class="llf-notice llf-notice--success">' . wp_kses_post($success_message) . '</div>'
                    . '</div>';
            }
        }

        if (empty($fields)) {
            return '';
        }

        if (FormBuilder::recaptchaActive($form['settings'] ?? [])) {
            // Enqueued only when a rendered form actually needs it, rather than
            // on every page of the site.
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js',
                [],
                null,
                true
            );
        }

        $values = (array) ($args['values'] ?? []);
        $errors = (array) ($args['errors'] ?? []);
        $notice = (string) ($args['notice'] ?? '');

        $submit_label = trim((string) ($form['settings']['submit_label'] ?? ''));

        if ('' === $submit_label) {
            $submit_label = FormBuilder::defaultSubmitLabel();
        }

        $submit_label = Strings::get($submit_label, (string) ($form['settings']['submit_key'] ?? ''));

        ob_start();
        include LLF_DIR . 'templates/form.php';

        return (string) ob_get_clean();
    }

    /**
     * Markup for one field.
     *
     * @param array $field  Normalised definition.
     * @param mixed $value  Previously submitted value, if redisplaying.
     * @param string $error Message for this field, if any.
     */
    public static function field(array $field, $value = null, string $error = ''): string
    {
        $name  = (string) $field['name'];
        $label = Strings::get((string) $field['label'], (string) ($field['translation_key'] ?? ''));
        $id    = 'llf-' . $name;
        $type  = (string) $field['type'];
        $req   = !empty($field['required']);
        $attrs = [
            'id'    => $id,
            'name'  => 'llf[' . $name . ']',
            // A class on the input itself, not only on the wrapper: colouring
            // the border of the thing that is wrong is the usual way to show
            // it, and a stylesheet should not have to reach in from a parent.
            'class' => 'llf-input llf-input--' . $type . ($error ? ' llf-input--error' : ''),
        ];

        if ($error) {
            // Announced as well as coloured. Someone using a screen reader gets
            // no benefit from a red border.
            $attrs['aria-invalid']      = 'true';
            $attrs['aria-describedby']  = $id . '-error';
        }

        if ($req) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = Strings::get(
                (string) $field['placeholder'],
                (string) ($field['placeholder_key'] ?? '')
            );
        }

        if (!empty($field['max_length'])) {
            $attrs['maxlength'] = (string) (int) $field['max_length'];
        }

        // The browser check is a convenience; the same rules are enforced again
        // server-side, where they cannot be edited away.
        if ('email' === ($field['validation'] ?? '')) {
            $attrs['type'] = 'email';
        }

        if ('regex' === ($field['validation'] ?? '') && '' !== (string) $field['pattern']) {
            $attrs['pattern'] = (string) $field['pattern'];
        }

        $out = '<p class="llf-field llf-field--' . esc_attr($type) . ($error ? ' llf-field--error' : '') . '">';

        if ('checkbox' === $type) {
            $checked = null === $value ? !empty($field['checked']) : (bool) $value;

            $out .= '<label for="' . esc_attr($id) . '">';
            $out .= '<input type="checkbox" value="1"' . self::attrs($attrs) . checked($checked, true, false) . '> ';
            $out .= esc_html($label) . ($req ? ' <span class="llf-required">*</span>' : '');
            $out .= '</label>';
        } else {
            $out .= '<label for="' . esc_attr($id) . '">' . esc_html($label);
            $out .= $req ? ' <span class="llf-required">*</span>' : '';
            $out .= '</label>';

            if ('textarea' === $type) {
                $out .= '<textarea rows="6"' . self::attrs($attrs) . '>' . esc_textarea((string) $value) . '</textarea>';
            } else {
                $attrs['type']  = $attrs['type'] ?? 'text';
                $attrs['value'] = (string) $value;
                $out .= '<input' . self::attrs($attrs) . '>';
            }
        }

        if ($error) {
            $out .= '<span class="llf-error" id="' . esc_attr($id) . '-error">' . esc_html($error) . '</span>';
        }

        return $out . '</p>';
    }

    /**
     * @param array<string, string> $attrs
     */
    private static function attrs(array $attrs): string
    {
        $out = '';

        foreach ($attrs as $key => $value) {
            $out .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        return $out;
    }
}
