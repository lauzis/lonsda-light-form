<?php

namespace LonsdaLightForm;

/**
 * Puts the site into a particular language for the length of a piece of work.
 *
 * WordPress translates against the locale of the current request, which is the
 * right answer for a page being rendered and the wrong one for a message being
 * written to somebody else. The auto reply is the clear case: it is read by the
 * person who filled the form in, so it belongs in the language they were
 * reading, whatever language the request sending it happens to be in.
 *
 * Three things have to move together, which is why this is not a bare call to
 * switch_to_locale():
 *
 * - The locale, for the plugin's own wording — the shipped defaults.
 * - The form content text domain, which lives in a directory of its own (see
 *   Translations) and so is not one of the domains core reloads on a switch.
 * - WPML's current language, which switch_to_locale() knows nothing about and
 *   which decides what Strings::get() is handed back.
 *
 * Every switch has to be undone. Call restore() from a finally, so a fatal in
 * between does not leave the rest of the request in someone else's language.
 */
class Locale
{
    /**
     * What each switch has to undo, innermost last.
     *
     * A stack rather than a flag: restore() undoes one switch rather than
     * returning to wherever the request started, or two overlapping switches
     * would leave the outer one's caller in the wrong language.
     *
     * @var array<int, array{wpml: string, core: bool}>
     */
    private static $stack = [];

    /**
     * Switches to a language, unless it is the one already in use.
     *
     * @param string $locale   Full locale, e.g. "lv_LV".
     * @param string $language Language code as the translation plugin reports
     *                         it, e.g. "lv" or "zh-hans". Taken from the
     *                         submission rather than cut off the locale, since
     *                         only WPML knows what it calls its own languages.
     * @return bool Whether anything changed, and so whether restore() is owed.
     */
    public static function switchTo(string $locale, string $language = ''): bool
    {
        $locale = Translations::sanitizeLocale($locale);

        if ('' === $locale || $locale === determine_locale()) {
            return false;
        }

        // False here means core has no files for the locale, not that nothing
        // happened: it refuses a locale it cannot serve. The form's own strings
        // are ours and live elsewhere, so those still switch — the visitor gets
        // their own wording translated even on a site that never downloaded the
        // WordPress translation for their language.
        $core = switch_to_locale($locale);
        $wpml = self::switchWpml($language ?: self::code($locale));

        if (!$core) {
            // Nothing fired change_locale, so the form strings have to be
            // pointed at the language by hand.
            Translations::load($locale);
        }

        self::$stack[] = ['wpml' => $wpml, 'core' => $core];

        return true;
    }

    /** Undoes one switchTo() that returned true. */
    public static function restore(): void
    {
        if (!self::$stack) {
            return;
        }

        $frame = array_pop(self::$stack);

        if ('' !== $frame['wpml']) {
            self::switchWpml($frame['wpml']);
        }

        if ($frame['core']) {
            // Fires change_locale again, which puts the form strings back.
            restore_previous_locale();

            return;
        }

        // Nothing moved the locale, so nothing will reload the form strings.
        Translations::load();
    }

    /**
     * Points WPML at a language.
     *
     * @param string $language Code as WPML reports it.
     * @return string The language it was on, for restore() to switch back to,
     *                or '' when WPML is not running. Recorded as a code rather
     *                than relying on WPML's own "back to the original", which
     *                is not the same place when one switch is nested in
     *                another.
     */
    private static function switchWpml(string $language): string
    {
        if ('' === $language || !has_filter('wpml_current_language')) {
            return '';
        }

        $previous = (string) apply_filters('wpml_current_language', null);

        do_action('wpml_switch_language', $language);

        return $previous;
    }

    /** The language part of a locale: "lv_LV" becomes "lv". */
    private static function code(string $locale): string
    {
        if (class_exists('\\Lauzis\\WpPackages\\I18n\\Language')) {
            return \Lauzis\WpPackages\I18n\Language::normalize($locale);
        }

        $parts = preg_split('/[_-]/', $locale);

        return is_array($parts) ? strtolower($parts[0]) : '';
    }
}
