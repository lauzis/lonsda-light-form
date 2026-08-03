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
     *     @type array  $values Values to re-populate with, after a failed submit.
     *     @type array  $errors Field name => message.
     *     @type string $notice Message shown above the form.
     * }
     */
    public static function form(int $id, array $args = []): string
    {
        $form = Forms::get($id);

        if (!$form) {
            // Deliberately quiet on the front end: a mistyped id in a shortcode
            // should not print an error into someone's page.
            Logs::error('render', 'A form was requested that does not exist.', ['id' => $id]);

            return current_user_can('manage_options')
                ? '<p><em>' . esc_html(sprintf(__('Lonsda: no form with id %d.', 'lonsda-light-form'), $id)) . '</em></p>'
                : '';
        }

        $fields = $form['settings']['fields'] ?? [];

        if (empty($fields)) {
            return '';
        }

        if (!empty($form['settings']['recaptcha'])) {
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
        $id    = 'llf-' . $name;
        $type  = (string) $field['type'];
        $req   = !empty($field['required']);
        $attrs = [
            'id'   => $id,
            'name' => 'llf[' . $name . ']',
        ];

        if ($req) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = (string) $field['placeholder'];
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
            $out .= esc_html($field['label']) . ($req ? ' <span class="llf-required">*</span>' : '');
            $out .= '</label>';
        } else {
            $out .= '<label for="' . esc_attr($id) . '">' . esc_html($field['label']);
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
            $out .= '<span class="llf-error">' . esc_html($error) . '</span>';
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
