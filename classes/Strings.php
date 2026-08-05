<?php

namespace LonsdaLightForm;

/**
 * Translates a form's own wording — labels, placeholders, the submit button.
 *
 * These strings are typed into the editor, so `wp i18n make-pot` cannot see
 * them. Each carries a translation key instead, and this decides what that key
 * means.
 *
 * Two sources, tried in order. WPML first, because a site running it manages
 * strings in its editor and expects that to win. Then gettext, against MO files
 * in wp-content/languages/ — see Translations — which is what a site without
 * WPML uses. Anything else hooks the filter.
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
        // call whether or not anyone has translated anything yet — and a result
        // that differs from the original is the only reliable sign it had one.
        $translated = (string) apply_filters('wpml_translate_single_string', $text, self::CONTEXT, $key);

        if ($translated === $text) {
            // Nothing from WPML, so try the gettext files. Both are offered
            // because a site has one or the other far more often than both:
            // WPML sites translate in its editor, everyone else wants a .po
            // they can open in Poedit.
            $translated = translate_with_gettext_context($text, $key, Translations::DOMAIN);
        }

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
            self::registerOne((string) ($field['placeholder_key'] ?? ''), (string) ($field['placeholder'] ?? ''));
        }

        self::registerOne(
            (string) ($settings['submit_key'] ?? ''),
            (string) ($settings['submit_label'] ?? '') ?: FormBuilder::defaultSubmitLabel()
        );

        foreach (self::formStrings($settings) as $key => $text) {
            self::registerOne($key, $text);
        }
    }

    /**
     * The form's own wording — what it says rather than what it asks.
     *
     * Gathered in one place so the translations page, the registration above
     * and anything else all agree on what a form consists of.
     *
     * @param array $settings Stored form definition.
     * @return array<string, string> Key => original text.
     */
    public static function formStrings(array $settings): array
    {
        $strings = [];

        $pairs = [
            'success_key'            => ['success_message', [FormBuilder::class, 'defaultSuccessMessage']],
            'notify_subject_key'     => ['notify_subject', null],
            'notify_message_key'     => ['notify_message', null],
            'auto_reply_subject_key' => ['auto_reply_subject', [FormBuilder::class, 'defaultAutoReplySubject']],
            'auto_reply_message_key' => ['auto_reply_message', [FormBuilder::class, 'defaultAutoReplyMessage']],
        ];

        foreach ($pairs as $keyName => [$valueName, $fallback]) {
            $key  = (string) ($settings[$keyName] ?? '');
            $text = trim((string) ($settings[$valueName] ?? ''));

            // The shipped default stands in for an empty box, because that is
            // what gets sent — translating nothing would translate nothing.
            if ('' === $text && $fallback) {
                $text = (string) call_user_func($fallback);
            }

            if ('' !== $key && '' !== $text) {
                $strings[$key] = $text;
            }
        }

        return $strings;
    }

    private static function registerOne(string $key, string $text): void
    {
        if ('' === $key || '' === $text) {
            return;
        }

        do_action('wpml_register_single_string', self::CONTEXT, $key, $text);
    }
}
