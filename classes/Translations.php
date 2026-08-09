<?php

namespace LonsdaLightForm;

/**
 * Gettext translations for wording that lives in the database.
 *
 * Field labels and button text are typed into the editor, so `wp i18n make-pot`
 * cannot see them — it scans source, and these are rows in a table. This
 * generates the POT from the stored forms instead, and loads whatever MO comes
 * back.
 *
 * Files live in wp-content/languages/lonsda-light-form/, not in the plugin
 * directory: WordPress deletes and re-extracts a plugin folder on every update,
 * which would take the translations with it. Under its own text domain rather
 * than the plugin's, so uploading form translations cannot overwrite the
 * plugin's own interface translations.
 *
 * The translation key is the gettext context, and the label typed into the
 * editor is the msgid. That way an untranslated string falls back to the label
 * a person wrote, rather than to "field_email_label".
 */
class Translations
{
    /** Text domain for form content, kept apart from the plugin's own. */
    public const DOMAIN = 'lonsda-forms';

    /** What a string is part of, so the editor can group rather than list. */
    public const GROUP_FIELDS       = 'fields';
    public const GROUP_CONFIRMATION = 'confirmation';
    public const GROUP_NOTIFICATION = 'notification';
    public const GROUP_AUTO_REPLY   = 'auto_reply';

    /**
     * Group => heading, in the order the editor shows them.
     *
     * Roughly the order somebody meets them: the fields they filled in, the
     * button they pressed, what they were told, then the two emails.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            // The submit button sits with the fields: it is one string, and a
            // heading of its own over a single row is more furniture than help.
            self::GROUP_FIELDS       => __('Form fields', 'lonsda-light-form'),
            self::GROUP_CONFIRMATION => __('Confirmation message', 'lonsda-light-form'),
            self::GROUP_NOTIFICATION => __('Notification email', 'lonsda-light-form'),
            self::GROUP_AUTO_REPLY   => __('Auto reply email', 'lonsda-light-form'),
        ];
    }

    /**
     * The same strings, split by group and in display order.
     *
     * Returned already grouped because the editor renders a table per section:
     * asking it to detect where one group ends and the next begins would put
     * the ordering rule in the template, where a later change to sorting would
     * silently break the headings.
     *
     * @param int $form_id
     * @return array<string, array<string, array>> Group => key => entry.
     */
    public static function grouped(int $form_id = 0): array
    {
        $grouped = [];

        foreach (array_keys(self::groups()) as $group) {
            $grouped[$group] = [];
        }

        foreach (self::strings($form_id) as $key => $entry) {
            $group = $entry['group'] ?? self::GROUP_FIELDS;

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][$key] = $entry;
        }

        // A form with no auto reply, say, should not get an empty table.
        return array_filter($grouped);
    }

    /** Which group a form-level key belongs to. */
    private static function groupForKey(string $keyName): string
    {
        if (0 === strpos($keyName, 'auto_reply')) {
            return self::GROUP_AUTO_REPLY;
        }

        if (0 === strpos($keyName, 'notify')) {
            return self::GROUP_NOTIFICATION;
        }

        return self::GROUP_CONFIRMATION;
    }

    /** Largest upload accepted. A translation file for one site is tiny. */
    public const MAX_UPLOAD = 2097152;

    /** Loads the file for the current locale, if there is one. */
    public static function init(): void
    {
        // Late on init: the locale is not settled until translation plugins
        // have had their say about which language this request is in.
        add_action('init', [self::class, 'load'], 20);
    }

    public static function load(): void
    {
        $locale = determine_locale();
        $path   = self::path($locale);

        if ($locale && is_readable($path)) {
            load_textdomain(self::DOMAIN, $path);
        }
    }

    /** Where a locale's compiled translations live. */
    public static function path(string $locale): string
    {
        return self::directory() . '/' . self::DOMAIN . '-' . $locale . '.mo';
    }

    /**
     * Where the editable source sits, beside the compiled file.
     *
     * Both are written on every save. The MO is what gettext reads; the PO is
     * what a person opens in Poedit, and without it a translation could only
     * ever be edited here.
     */
    public static function poPath(string $locale): string
    {
        return self::directory() . '/' . self::DOMAIN . '-' . $locale . '.po';
    }

    /**
     * Translations already recorded for a locale.
     *
     * @return array<string, string> Translation key => translated text.
     */
    public static function existing(string $locale): array
    {
        $path = self::path($locale);

        if (!is_readable($path)) {
            return [];
        }

        if (!class_exists('\MO')) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
        }

        $mo = new \MO();

        if (!$mo->import_from_file($path)) {
            return [];
        }

        $found = [];

        foreach ($mo->entries as $entry) {
            // The context is the translation key; entries without one are not
            // ours and are left alone rather than shown as if they were.
            if (!empty($entry->context)) {
                $found[$entry->context] = (string) ($entry->translations[0] ?? '');
            }
        }

        return $found;
    }

    /**
     * Records translations for a locale, keeping any it was not shown.
     *
     * The editor works one form at a time, so a save must not be read as "these
     * are all the translations there are" — merging is what stops translating
     * one form from wiping another.
     *
     * @param array<string, string> $pairs Translation key => translated text.
     * @return true|\WP_Error
     */
    public static function save(string $locale, array $pairs)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('llf_denied', __('You are not allowed to edit translations.', 'lonsda-light-form'));
        }

        $locale = self::sanitizeLocale($locale);

        if ('' === $locale) {
            return new \WP_Error('llf_bad_locale', __('That is not a valid locale.', 'lonsda-light-form'));
        }

        if (!class_exists('\MO')) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
        }

        if (!class_exists('\PO')) {
            require_once ABSPATH . WPINC . '/pomo/po.php';
        }

        $merged  = self::existing($locale);
        $strings = self::strings();

        foreach ($pairs as $key => $text) {
            $key  = (string) $key;
            $text = trim((string) $text);

            // An emptied box removes the translation rather than storing an
            // empty one, which gettext would treat as untranslated anyway while
            // leaving a misleading entry in the file.
            if ('' === $text) {
                unset($merged[$key]);

                continue;
            }

            $merged[$key] = $text;
        }

        $mo          = new \MO();
        $mo->headers = self::headers($locale);

        foreach ($merged as $key => $text) {
            // Skipped when the original is gone: a translation with nothing
            // left to translate is dead weight, and its msgid is unknown.
            if (!isset($strings[$key])) {
                continue;
            }

            $mo->add_entry(new \Translation_Entry([
                'context'      => $key,
                'singular'     => $strings[$key]['text'],
                'translations' => [$text],
            ]));
        }

        if (!$mo->export_to_file(self::path($locale))) {
            return new \WP_Error(
                'llf_not_written',
                sprintf(
                    /* translators: %s: directory path */
                    __('Could not write to %s. Check the directory is writable.', 'lonsda-light-form'),
                    self::directory()
                )
            );
        }

        $po          = new \PO();
        $po->headers = $mo->headers;
        $po->entries = $mo->entries;
        $po->export_to_file(self::poPath($locale));

        Logs::add('translations', 'Translations saved from the editor.', [
            'locale'  => $locale,
            'entries' => count($mo->entries),
        ]);

        return true;
    }

    /**
     * PO/MO headers, without which the file is not quite a translation file.
     *
     * @return array<string, string>
     */
    private static function headers(string $locale): array
    {
        return [
            'Project-Id-Version'        => self::DOMAIN,
            'Language'                  => $locale,
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'PO-Revision-Date'          => gmdate('Y-m-d H:iO'),
            'X-Generator'               => 'Lonsda Light Form ' . LLF_VERSION,
        ];
    }

    /** The directory holding them, created on demand. */
    public static function directory(): string
    {
        $dir = rtrim(WP_LANG_DIR, '/\\') . '/' . LLF_SLUG;

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }

    /**
     * Every translatable string across every form.
     *
     * Keyed by translation key, so two forms sharing a field name share one
     * entry — which is the point of the key being derived from the name.
     *
     * @return array<string, array{text: string, forms: string[]}>
     */
    public static function strings(int $form_id = 0): array
    {
        $strings = [];

        foreach (Forms::all() as $row) {
            if ($form_id > 0 && (int) $row->id !== $form_id) {
                continue;
            }

            $settings = json_decode((string) $row->settings, true);

            if (!is_array($settings)) {
                continue;
            }

            $title = (string) $row->title;

            foreach ($settings['fields'] ?? [] as $field) {
                self::collect($strings, (string) ($field['translation_key'] ?? ''), (string) ($field['label'] ?? ''), $title);
                // collect() ignores an empty string, so a field with no
                // placeholder simply does not appear — which is right: that is
                // a field without one, not a string waiting to be translated.
                self::collect($strings, (string) ($field['placeholder_key'] ?? ''), (string) ($field['placeholder'] ?? ''), $title);
            }

            self::collect(
                $strings,
                (string) ($settings['submit_key'] ?? ''),
                (string) ($settings['submit_label'] ?? '') ?: FormBuilder::defaultSubmitLabel(),
                $title,
                self::GROUP_FIELDS
            );

            // The form's own wording — confirmation, notification, auto reply.
            // Keyed by the form's text id rather than shared, because two forms
            // saying "Thank you" may well want to say it differently.
            foreach (Strings::formStrings($settings) as $keyName => $pair) {
                self::collect($strings, $pair['key'], $pair['text'], $title, self::groupForKey($keyName));
            }
        }

        // By group, then by the order they were collected in. Alphabetical was
        // never meaningful and actively wrong for a form's own messages, where
        // it put the body above the subject — an email is written subject
        // first. Collection order is the order a person meets things: fields as
        // they appear on the form, then subject before body.
        $order = array_keys(self::groups());

        uasort(
            $strings,
            static function ($a, $b) use ($order) {
                $ga = array_search($a['group'] ?? self::GROUP_FIELDS, $order, true);
                $gb = array_search($b['group'] ?? self::GROUP_FIELDS, $order, true);

                if ($ga !== $gb) {
                    return $ga <=> $gb;
                }

                return ($a['seq'] ?? 0) <=> ($b['seq'] ?? 0);
            }
        );

        return $strings;
    }

    /**
     * @param array<string, array{text: string, forms: string[]}> $strings
     */
    private static function collect(array &$strings, string $key, string $text, string $form, string $group = self::GROUP_FIELDS): void
    {
        if ('' === $key || '' === $text) {
            return;
        }

        if (!isset($strings[$key])) {
            // Position in collection order, which is what the listing sorts by
            // within a group. Shared across forms: a key first seen on form one
            // keeps its place when form two mentions it again.
            static $seq = 0;

            $strings[$key] = ['text' => $text, 'forms' => [], 'group' => $group, 'seq' => ++$seq];
        }

        if (!in_array($form, $strings[$key]['forms'], true)) {
            $strings[$key]['forms'][] = $form;
        }
    }

    /**
     * A POT file describing the current forms.
     *
     * Regenerate it after changing a form: it is a snapshot, not a live view.
     */
    public static function pot(): string
    {
        $now   = gmdate('Y-m-d H:iO');
        $lines = [
            '# Translations for forms built with Lonsda Light Form.',
            '#',
            '# GENERATED from the forms stored on ' . home_url() . '.',
            '# Regenerate after adding or renaming a field — this is a snapshot.',
            '#',
            '# The context (msgctxt) is the translation key shown on the form editor.',
            '# The msgid is the label as typed, so an untranslated string still reads',
            '# as something a person wrote.',
            'msgid ""',
            'msgstr ""',
            '"Project-Id-Version: ' . self::DOMAIN . '\\n"',
            '"Report-Msgid-Bugs-To: \\n"',
            '"POT-Creation-Date: ' . $now . '\\n"',
            '"MIME-Version: 1.0\\n"',
            '"Content-Type: text/plain; charset=UTF-8\\n"',
            '"Content-Transfer-Encoding: 8bit\\n"',
            '"X-Generator: Lonsda Light Form ' . LLF_VERSION . '\\n"',
            '',
        ];

        foreach (self::strings() as $key => $entry) {
            $lines[] = '#. Used by: ' . implode(', ', $entry['forms']);
            $lines[] = 'msgctxt "' . self::escape($key) . '"';
            $lines[] = 'msgid "' . self::escape($entry['text']) . '"';
            $lines[] = 'msgstr ""';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** Escapes a string for a PO literal. */
    private static function escape(string $text): string
    {
        return str_replace(
            ["\\", '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $text
        );
    }

    /**
     * Translation files already installed.
     *
     * @return array<string, array{locale: string, path: string, size: int, modified: int, entries: int}>
     */
    public static function installed(): array
    {
        $found = [];
        $glob  = glob(self::directory() . '/' . self::DOMAIN . '-*.mo');

        foreach ($glob ?: [] as $path) {
            $locale = substr(basename($path), strlen(self::DOMAIN) + 1, -3);

            $found[$locale] = [
                'locale'   => $locale,
                'path'     => $path,
                'size'     => (int) filesize($path),
                'modified' => (int) filemtime($path),
                'entries'  => self::countEntries($path),
            ];
        }

        ksort($found);

        return $found;
    }

    /** How many strings a compiled file carries, for the status table. */
    private static function countEntries(string $path): int
    {
        if (!class_exists('\MO')) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
        }

        $mo = new \MO();

        if (!$mo->import_from_file($path)) {
            return 0;
        }

        return count($mo->entries);
    }

    /**
     * Locales worth offering, so a file gets the name gettext will look for.
     *
     * A translation plugin, where there is one, is the authority: it decides
     * which locale each page is served as, and that is the only name a file can
     * usefully have. WordPress's own installed-translations list is a fallback
     * for sites without one — used *instead of*, not as well as.
     *
     * Offering both produced two Latvians on a WPML site: lv_LV from WPML,
     * which is what pages are actually served as, and a bare lv from a language
     * pack that happened to be installed. Naming a file after the second would
     * have looked right and never been loaded.
     *
     * @return array<string, string> Locale => label.
     */
    public static function locales(): array
    {
        $locales = self::fromTranslationPlugin();

        if (!$locales) {
            foreach (get_available_languages() as $locale) {
                $locales[$locale] = $locale;
            }
        }

        $current = determine_locale();

        if ($current && !isset($locales[$current])) {
            $locales[$current] = $current;
        }

        if ($current && isset($locales[$current])) {
            $locales[$current] .= ' — ' . __('serving this page', 'lonsda-light-form');
        }

        asort($locales);

        return $locales;
    }

    /**
     * Languages as reported by WPML or Polylang.
     *
     * @return array<string, string> Locale => label.
     */
    private static function fromTranslationPlugin(): array
    {
        $locales = [];
        $active  = apply_filters('wpml_active_languages', null);

        if (is_array($active)) {
            foreach ($active as $language) {
                if (empty($language['default_locale'])) {
                    continue;
                }

                $locale             = (string) $language['default_locale'];
                $name               = (string) ($language['translated_name'] ?? $language['english_name'] ?? $locale);
                $locales[$locale]   = $name . ' (' . $locale . ')';
            }
        }

        if (!$locales && function_exists('pll_languages_list')) {
            $names   = (array) pll_languages_list(['fields' => 'name']);
            $codes   = (array) pll_languages_list(['fields' => 'locale']);

            foreach ($codes as $i => $locale) {
                $locales[(string) $locale] = isset($names[$i])
                    ? $names[$i] . ' (' . $locale . ')'
                    : (string) $locale;
            }
        }

        return $locales;
    }

    /**
     * Stores an uploaded translation file.
     *
     * @param array  $file   One entry from $_FILES.
     * @param string $locale Locale it is for.
     * @return true|\WP_Error
     */
    public static function store(array $file, string $locale)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('llf_denied', __('You are not allowed to upload translations.', 'lonsda-light-form'));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('llf_no_file', __('No file was uploaded.', 'lonsda-light-form'));
        }

        if (($file['size'] ?? 0) > self::MAX_UPLOAD) {
            return new \WP_Error('llf_too_big', __('That file is larger than a translation file should ever be.', 'lonsda-light-form'));
        }

        $locale = self::sanitizeLocale($locale);

        if ('' === $locale) {
            return new \WP_Error('llf_bad_locale', __('That is not a valid locale.', 'lonsda-light-form'));
        }

        $name      = (string) ($file['name'] ?? '');
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($extension, ['mo', 'po'], true)) {
            return new \WP_Error('llf_bad_type', __('Upload a .mo or .po file.', 'lonsda-light-form'));
        }

        // Compiled from the .po when that is what arrived, so the site always
        // stores the format gettext can actually read.
        $mo = 'mo' === $extension
            ? self::readMo($file['tmp_name'])
            : self::compilePo($file['tmp_name']);

        if (is_wp_error($mo)) {
            return $mo;
        }

        $path = self::path($locale);

        if (!$mo->export_to_file($path)) {
            return new \WP_Error(
                'llf_not_written',
                sprintf(
                    /* translators: %s: directory path */
                    __('Could not write to %s. Check the directory is writable.', 'lonsda-light-form'),
                    self::directory()
                )
            );
        }

        Logs::add('translations', 'Translation file stored.', [
            'locale'  => $locale,
            'entries' => count($mo->entries),
            'from'    => $extension,
        ]);

        return true;
    }

    /**
     * @param string $path
     * @return \MO|\WP_Error
     */
    private static function readMo(string $path)
    {
        if (!class_exists('\MO')) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
        }

        $mo = new \MO();

        // Parsed rather than copied: a file that does not parse is not a
        // translation file, whatever it is called, and finding that out now is
        // better than a silent no-op on the front end.
        if (!$mo->import_from_file($path)) {
            return new \WP_Error('llf_bad_mo', __('That file could not be read as a compiled translation.', 'lonsda-light-form'));
        }

        return $mo;
    }

    /**
     * @param string $path
     * @return \MO|\WP_Error
     */
    private static function compilePo(string $path)
    {
        if (!class_exists('\PO')) {
            require_once ABSPATH . WPINC . '/pomo/po.php';
        }

        if (!class_exists('\MO')) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
        }

        $po = new \PO();

        if (!$po->import_from_file($path)) {
            return new \WP_Error('llf_bad_po', __('That file could not be read as a translation source.', 'lonsda-light-form'));
        }

        $mo          = new \MO();
        $mo->headers = $po->headers;
        $mo->entries = $po->entries;

        return $mo;
    }

    /** Removes a locale's file. */
    public static function delete(string $locale): bool
    {
        $locale = self::sanitizeLocale($locale);
        $path   = self::path($locale);

        if ('' === $locale || !file_exists($path)) {
            return false;
        }

        // The path is built from a sanitised locale inside a directory we own,
        // so there is nothing here a request could point at instead.
        $deleted = unlink($path);

        if ($deleted) {
            Logs::add('translations', 'Translation file removed.', ['locale' => $locale]);
        }

        return $deleted;
    }

    /**
     * A locale is letters, digits and underscores — nothing that could climb
     * out of the directory or name a file we did not mean.
     */
    public static function sanitizeLocale(string $locale): string
    {
        $locale = preg_replace('/[^A-Za-z0-9_\-]/', '', $locale);

        return is_string($locale) ? substr($locale, 0, 20) : '';
    }
}
