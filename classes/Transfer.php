<?php

namespace LonsdaLightForm;

/**
 * Moving form definitions between sites as JSON.
 *
 * Exports what a form is, not where it lives: no ids, no post references, no
 * entries. An id means nothing on the site it lands on, and entries are records
 * of what people sent rather than part of the design.
 *
 * Import writes through the same path the editor uses — Carbon Fields meta,
 * then the usual projection into the table — so an imported form is identical
 * to one built by hand, and nothing has to know it arrived from a file.
 */
class Transfer
{
    /** Marks a file as ours, so a stray JSON is refused rather than half-read. */
    public const FORMAT = 'lonsda-light-form/forms';

    /** Bumped only if the shape changes in a way an older import cannot read. */
    public const FORMAT_VERSION = 1;

    /** Largest upload accepted. An export of a whole site is still small. */
    public const MAX_UPLOAD = 5242880;

    /**
     * Form settings that live on the post rather than in a field row.
     *
     * Definition key => Carbon Fields meta key. Kept as one list so export and
     * import cannot disagree about what a form consists of.
     */
    private const FORM_KEYS = [
        'submit_label'    => 'llf_submit_label',
        'success_message' => 'llf_success_message',
        'hide_on_success' => 'llf_hide_on_success',
        'notify_to'       => 'llf_notify_to',
        'notify_subject'  => 'llf_notify_subject',
        'notify_message'  => 'llf_notify_message',
        'notify_reply_to' => 'llf_notify_reply_to',
        'store_entries'   => 'llf_store_entries',
        'recaptcha'       => 'llf_recaptcha',
    ];

    /**
     * @param int[] $ids Form ids to include. Empty means every form.
     */
    public static function export(array $ids = []): string
    {
        $forms = [];

        foreach (Forms::all() as $row) {
            if ($ids && !in_array((int) $row->id, $ids, true)) {
                continue;
            }

            $settings = json_decode((string) $row->settings, true);

            $forms[] = [
                // Recorded for whoever reads the file, not used on import: an
                // id is a fact about the site it came from.
                'source_id' => (int) $row->id,
                'title'     => (string) $row->title,
                'settings'  => is_array($settings) ? $settings : [],
            ];
        }

        return (string) wp_json_encode(
            [
                'format'      => self::FORMAT,
                'version'     => self::FORMAT_VERSION,
                'generator'   => 'Lonsda Light Form ' . LLF_VERSION,
                'exported_at' => gmdate('c'),
                'site'        => home_url(),
                'forms'       => $forms,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Creates forms from an exported file.
     *
     * Always creates rather than overwrites. Matching by title would be the
     * only way to update, and two forms may legitimately share one — quietly
     * replacing the wrong form is worse than leaving a duplicate to delete.
     *
     * @return array{created: string[], errors: string[]}
     */
    public static function import(string $json): array
    {
        $report = ['created' => [], 'errors' => []];

        if (!current_user_can('manage_options')) {
            $report['errors'][] = __('You are not allowed to import forms.', 'lonsda-light-form');

            return $report;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            $report['errors'][] = __('That is not valid JSON.', 'lonsda-light-form');

            return $report;
        }

        if (self::FORMAT !== ($data['format'] ?? '')) {
            $report['errors'][] = __('That file was not exported by this plugin.', 'lonsda-light-form');

            return $report;
        }

        if ((int) ($data['version'] ?? 0) > self::FORMAT_VERSION) {
            $report['errors'][] = __('That file was exported by a newer version of the plugin than this one.', 'lonsda-light-form');

            return $report;
        }

        $forms = $data['forms'] ?? null;

        if (!is_array($forms) || !$forms) {
            $report['errors'][] = __('That file contains no forms.', 'lonsda-light-form');

            return $report;
        }

        foreach ($forms as $form) {
            if (!is_array($form)) {
                continue;
            }

            $title  = trim((string) ($form['title'] ?? ''));
            $result = self::createForm($title, (array) ($form['settings'] ?? []));

            if (is_wp_error($result)) {
                $report['errors'][] = sprintf(
                    /* translators: 1: form title, 2: error message */
                    __('%1$s could not be imported: %2$s', 'lonsda-light-form'),
                    $title ?: __('(untitled)', 'lonsda-light-form'),
                    $result->get_error_message()
                );

                continue;
            }

            $report['created'][] = $title;
        }

        Logs::add('transfer', 'Forms imported.', [
            'created' => count($report['created']),
            'errors'  => count($report['errors']),
        ]);

        return $report;
    }

    /**
     * @param array $settings A definition as produced by FormBuilder.
     * @return int|\WP_Error Post id of the new form.
     */
    private static function createForm(string $title, array $settings)
    {
        if ('' === $title) {
            $title = __('Imported form', 'lonsda-light-form');
        }

        $post_id = wp_insert_post([
            'post_type'   => Forms::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!function_exists('carbon_set_post_meta')) {
            return new \WP_Error('llf_no_carbon', __('Carbon Fields is unavailable.', 'lonsda-light-form'));
        }

        carbon_set_post_meta((int) $post_id, 'llf_fields', self::rowsFromDefinition($settings));

        foreach (self::FORM_KEYS as $key => $meta) {
            if (array_key_exists($key, $settings)) {
                carbon_set_post_meta((int) $post_id, $meta, $settings[$key]);
            }
        }

        // Through the ordinary projection, so an imported form is indexed and
        // renderable the same as any other and no step is special-cased.
        Forms::syncToTable((int) $post_id, get_post((int) $post_id));

        return (int) $post_id;
    }

    /**
     * Turns a stored definition back into editor rows.
     *
     * The definition is normalised for rendering; the editor wants the shape it
     * saves. Doing this here keeps the asymmetry in one place instead of
     * spreading it through the importer.
     *
     * @return array[]
     */
    private static function rowsFromDefinition(array $settings): array
    {
        $rows = [];

        foreach ($settings['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $rows[] = [
                'label'           => (string) ($field['label'] ?? ''),
                'name'            => (string) ($field['name'] ?? ''),
                'type'            => (string) ($field['type'] ?? 'text'),
                'placeholder'     => (string) ($field['placeholder'] ?? ''),
                'default_checked' => !empty($field['checked']),
                'required'        => !empty($field['required']),
                'validation'      => (string) ($field['validation'] ?? ''),
                'pattern'         => (string) ($field['pattern'] ?? ''),
                'max_length'      => (string) ($field['max_length'] ?? ''),
                // Translation keys are not carried: they are derived from the
                // field name, so the importing site works them out for itself
                // and an exported one could only ever disagree with it.
            ];
        }

        return $rows;
    }

    /** A filename that says what it is and when it was taken. */
    public static function filename(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return sanitize_file_name(
            'lonsda-forms-' . ($host ?: 'site') . '-' . gmdate('Y-m-d') . '.json'
        );
    }
}
