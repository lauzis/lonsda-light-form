<?php

namespace LonsdaLightForm;

/**
 * Translates a form's own wording — labels, placeholders, the submit button.
 *
 * These strings are typed into the editor, so they are not in the .pot file and
 * gettext cannot reach them. Each carries a translation key instead, and this
 * decides what that key means.
 *
 * WPML is handled directly because it is the common case and its string
 * translation is exactly this: a key, a context, and an original. Anything else
 * hooks the filter.
 */
class Strings
{
    /** Context WPML groups these strings under. */
    public const CONTEXT = 'Lonsda Light Form';

    /**
     * The translation of $text, or $text itself when nothing translates it.
     *
     * @param string $text Original wording, as typed into the editor.
     * @param string $key  Translation key for this string.
     */
    public static function get(string $text, string $key): string
    {
        if ('' === $text || '' === $key) {
            return $text;
        }

        // WPML returns the original untouched when the string is unregistered
        // or has no translation in the current language, so this is safe to
        // call whether or not anyone has translated anything yet.
        $translated = (string) apply_filters('wpml_translate_single_string', $text, self::CONTEXT, $key);

        /**
         * Filters a form string after the built-in translation layer.
         *
         * @param string $translated Result so far.
         * @param string $text       Original wording.
         * @param string $key        Translation key.
         * @param string $context    Grouping context.
         */
        return (string) apply_filters('lonsda_form_string', $translated, $text, $key, self::CONTEXT);
    }

    /**
     * Makes a form's strings available to translate.
     *
     * Called when a form is saved rather than when one is rendered: a string
     * nobody has visited yet should still be listed for a translator, and
     * registering on every front-end request would be work repeated for
     * nothing.
     *
     * @param array $settings Stored form definition.
     */
    public static function register(array $settings): void
    {
        foreach ($settings['fields'] ?? [] as $field) {
            self::registerOne((string) ($field['translation_key'] ?? ''), (string) ($field['label'] ?? ''));
        }

        self::registerOne(
            (string) ($settings['submit_key'] ?? ''),
            (string) ($settings['submit_label'] ?? '') ?: FormBuilder::defaultSubmitLabel()
        );
    }

    private static function registerOne(string $key, string $text): void
    {
        if ('' === $key || '' === $text) {
            return;
        }

        do_action('wpml_register_single_string', self::CONTEXT, $key, $text);
    }
}
