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
     * Marks a key as one of the plugin's own strings rather than a form's.
     *
     * A form's keys are derived from its text id or a field name, neither of
     * which can produce this prefix, so the two sets cannot collide.
     */
    public const GENERAL_PREFIX = 'general__';

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
     * The plugin's own wording that a visitor reads.
     *
     * Everything here used to be a plain __() against the plugin's text domain,
     * which meant translating it required a .po in a folder WordPress replaces
     * on every update, and put it out of reach of the screen where the rest of
     * a form's wording is translated. A site that had translated every label
     * still told people "This field is required." in English.
     *
     * So these go through the same layer as a form's own strings: WPML first,
     * then the form-content MO. The English here is the msgid, exactly as a
     * typed-in label is, so an untranslated string still reads as a sentence.
     *
     * Deliberately only what a visitor can see. Admin wording is translated the
     * ordinary way — whoever is reading it can also read the .po.
     *
     * Short names, prefixed on the way out: the call sites read better for it,
     * and the prefix is an implementation detail of the key rather than
     * something each caller should have to remember.
     *
     * @return array<string, string> Short name => English.
     */
    private static function catalogue(): array
    {
        return [
            // What a field says when it is filled in wrongly.
            'error_required'   => 'This field is required.',
            'error_checkbox'   => 'This box must be ticked.',
            'error_email'      => 'Please enter a valid email address.',
            'error_pattern'    => 'Please enter this in the expected format.',
            // Keeps its %d: the number is substituted after translation, so a
            // language that puts it elsewhere in the sentence can.
            'error_max_length' => 'Please use no more than %d characters.',
            'error_recaptcha'  => 'Please confirm you are not a robot.',

            // What the form says above itself, after a submission. The
            // confirmation is not here: that one is written per form, and is
            // translated with the form.
            'notice_errors'  => 'Please check the highlighted fields.',
            'notice_expired' => 'That form had been open too long to send, so nothing was sent. Your answers are still here — please send it again.',
            'notice_spam'    => 'Your message could not be sent. Please try again.',
            'notice_sent'    => 'Thank you — your message has been sent.',

            // A ticked box, written into an email as a word — and what an
            // unanswered optional field is listed as beside it.
            'word_yes'          => 'Yes',
            'word_no'           => 'No',
            'word_not_answered' => '(not answered)',
        ];
    }

    /**
     * One of those strings, translated.
     *
     * @param string $name Short name from the catalogue.
     */
    public static function general(string $name): string
    {
        $catalogue = self::catalogue();

        if (!isset($catalogue[$name])) {
            return '';
        }

        return self::get($catalogue[$name], self::GENERAL_PREFIX . $name);
    }

    /**
     * The catalogue as the translations screen wants it: key => English.
     *
     * @return array<string, string>
     */
    public static function generalStrings(): array
    {
        $strings = [];

        foreach (self::catalogue() as $name => $text) {
            $strings[self::GENERAL_PREFIX . $name] = $text;
        }

        return $strings;
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
        // Alongside the form's own, rather than on a hook of their own: these
        // never change, registering the same string twice costs nothing, and
        // saving a form is the moment somebody is demonstrably thinking about
        // this plugin's wording.
        foreach (self::generalStrings() as $key => $text) {
            self::registerOne($key, $text);
        }

        foreach ($settings['fields'] ?? [] as $field) {
            self::registerOne((string) ($field['translation_key'] ?? ''), (string) ($field['label'] ?? ''));
            self::registerOne((string) ($field['placeholder_key'] ?? ''), (string) ($field['placeholder'] ?? ''));
        }

        self::registerOne(
            (string) ($settings['submit_key'] ?? ''),
            (string) ($settings['submit_label'] ?? '') ?: FormBuilder::defaultSubmitLabel()
        );

        foreach (self::formStrings($settings) as $pair) {
            self::registerOne($pair['key'], $pair['text']);
        }
    }

    /**
     * The form's own wording — what it says rather than what it asks.
     *
     * Gathered in one place so the translations page, the registration above
     * and anything else all agree on what a form consists of.
     *
     * Keyed by the settings name — success_key, notify_message_key and so on —
     * rather than by the translation key, because the caller grouping these for
     * display needs to know which of the form's messages each one is, and the
     * translation key is opaque about that once a text id is prefixed to it.
     *
     * @param array $settings Stored form definition.
     * @return array<string, array{key: string, text: string}>
     */
    public static function formStrings(array $settings): array
    {
        $strings = [];

        $pairs = [
            'success_key'            => ['success_message', [FormBuilder::class, 'defaultSuccessMessage']],
            'notify_subject_key'     => ['notify_subject', null],
            'notify_message_key'     => ['notify_message', [FormBuilder::class, 'defaultNotificationMessage']],
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
                $strings[$keyName] = ['key' => $key, 'text' => $text];
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
