<?php

namespace LonsdaLightForm;

/**
 * Stored submissions.
 *
 * A listener on the public hook, exactly like Notifications — the submission
 * handler does not know either of them exists. Storing is what makes a
 * notification that never arrives merely annoying rather than a lost enquiry,
 * which is why it is on by default and the mail is not.
 *
 * An entry keeps each field's label and type next to its value rather than a
 * reference back to the form. Fields get renamed, retyped and deleted; an entry
 * has to stay readable as what was actually asked and answered at the time.
 */
class Entries
{
    /** Arrived, nobody has looked at it. */
    public const STATUS_NEW = 'new';

    /** Opened in the admin at least once. */
    public const STATUS_VIEWED = 'viewed';

    public static function init(): void
    {
        add_action(Submission::HOOK_SUBMITTED, [self::class, 'store'], 5, 3);
    }

    /**
     * @param array $values  Field name => submitted value.
     * @param array $form    Stored definition.
     * @param array $context Submission metadata.
     */
    public static function store(array $values, array $form, array $context): void
    {
        $settings = $form['settings'] ?? [];

        // Absent means a form saved before the setting existed, which is the
        // same case the field's default covers: keep the entry.
        $wanted = !array_key_exists('store_entries', $settings) || !empty($settings['store_entries']);

        if (!$wanted) {
            return;
        }

        global $wpdb;

        $rows = [];

        foreach ($settings['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');

            $rows[] = [
                'name'  => $name,
                'label' => (string) ($field['label'] ?? $name),
                'type'  => (string) ($field['type'] ?? 'text'),
                'value' => $values[$name] ?? null,
            ];
        }

        $inserted = $wpdb->insert(
            Migrations::entriesTableName(),
            [
                'form_id'      => (int) ($form['id'] ?? 0),
                'form_title'   => (string) ($form['title'] ?? ''),
                'post_id'      => isset($context['post_id']) ? (int) $context['post_id'] : null,
                'language'     => (string) ($context['language'] ?? ''),
                'ip'           => (string) ($context['ip'] ?? ''),
                'user_agent'   => (string) ($context['user_agent'] ?? ''),
                'data'         => wp_json_encode($rows),
                'status'       => self::STATUS_NEW,
                'submitted_at' => (string) ($context['submitted_at'] ?? gmdate('Y-m-d H:i:s')),
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $inserted) {
            Logs::error('entry', 'The submission could not be stored.', [
                'form'  => $form['id'] ?? null,
                'error' => $wpdb->last_error,
            ]);

            return;
        }

        Logs::add('entry', 'Submission stored.', [
            'form'  => $form['id'] ?? null,
            'entry' => (int) $wpdb->insert_id,
        ]);
    }

    /**
     * A page of entries, newest first.
     *
     * @param array $args {
     *     @type int $form_id Restrict to one form. 0 for all.
     *     @type int $per_page
     *     @type int $page     1-based.
     * }
     * @return object[]
     */
    public static function all(array $args = []): array
    {
        global $wpdb;

        $form_id  = (int) ($args['form_id'] ?? 0);
        $status   = (string) ($args['status'] ?? '');
        $per_page = max(1, min(200, (int) ($args['per_page'] ?? 25)));
        $page     = max(1, (int) ($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;
        $table    = Migrations::entriesTableName();

        [$where, $params] = self::conditions($form_id, $status);
        $params[]         = $per_page;
        $params[]         = $offset;

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY submitted_at DESC, id DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    /** How many entries there are, for paging and for the forms list. */
    public static function count(int $form_id = 0, string $status = ''): int
    {
        global $wpdb;

        $table            = Migrations::entriesTableName();
        [$where, $params] = self::conditions($form_id, $status);

        if (!$params) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", $params));
    }

    /**
     * Builds the shared WHERE clause, so listing and counting cannot drift
     * apart and report a total that does not match the rows shown.
     *
     * @return array{0: string, 1: array}
     */
    private static function conditions(int $form_id, string $status): array
    {
        $clauses = [];
        $params  = [];

        if ($form_id > 0) {
            $clauses[] = 'form_id = %d';
            $params[]  = $form_id;
        }

        if (in_array($status, [self::STATUS_NEW, self::STATUS_VIEWED], true)) {
            $clauses[] = 'status = %s';
            $params[]  = $status;
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** One entry, with its answers decoded. */
    public static function get(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Migrations::entriesTableName() . ' WHERE id = %d', $id)
        );

        return $row ? self::decode($row) : null;
    }

    /**
     * @param object $row
     * @return array
     */
    public static function decode($row): array
    {
        $data = json_decode((string) $row->data, true);

        return [
            'id'           => (int) $row->id,
            'form_id'      => (int) $row->form_id,
            'form_title'   => (string) $row->form_title,
            'post_id'      => null === $row->post_id ? null : (int) $row->post_id,
            'language'     => (string) $row->language,
            'ip'           => (string) $row->ip,
            'user_agent'   => (string) $row->user_agent,
            'submitted_at' => (string) $row->submitted_at,
            // Defaulted rather than read blindly: a row written before the
            // column existed has no status, and unread is the safer reading.
            'status'       => (string) ($row->status ?? self::STATUS_NEW) ?: self::STATUS_NEW,
            'fields'       => is_array($data) ? $data : [],
        ];
    }

    /**
     * Records that someone has looked at an entry.
     *
     * Called from the list when a row is expanded — opening it is the only
     * evidence of being read that exists, so it is what marks it.
     */
    public static function markViewed(int $id): bool
    {
        return self::setStatus($id, self::STATUS_VIEWED);
    }

    /** Sets an entry's status, refusing anything not a known one. */
    public static function setStatus(int $id, string $status): bool
    {
        global $wpdb;

        if (!in_array($status, [self::STATUS_NEW, self::STATUS_VIEWED], true)) {
            return false;
        }

        return false !== $wpdb->update(
            Migrations::entriesTableName(),
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * How many entries nobody has opened.
     *
     * Read on every admin page to draw the menu bubble, which is why status is
     * indexed.
     */
    public static function countNew(int $form_id = 0): int
    {
        global $wpdb;

        $table = Migrations::entriesTableName();

        // The table is missing until the migration has run, and an admin page
        // must not fatal because of that.
        if ($table !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return 0;
        }

        if ($form_id > 0) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s AND form_id = %d",
                    self::STATUS_NEW,
                    $form_id
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", self::STATUS_NEW)
        );
    }

    /** Removes one entry. */
    public static function delete(int $id): bool
    {
        global $wpdb;

        $deleted = (bool) $wpdb->delete(Migrations::entriesTableName(), ['id' => $id], ['%d']);

        if ($deleted) {
            Logs::add('entry', 'Entry deleted.', ['entry' => $id]);
        }

        return $deleted;
    }

    /**
     * Every form that has entries, for the filter dropdown.
     *
     * Read from the entries rather than from the forms table, so a form that
     * has since been deleted still appears — its entries did not go anywhere.
     *
     * @return array<int, string> Form id => title.
     */
    public static function formsWithEntries(): array
    {
        global $wpdb;

        $rows = (array) $wpdb->get_results(
            'SELECT form_id, form_title, COUNT(*) AS total FROM ' . Migrations::entriesTableName()
            . ' GROUP BY form_id, form_title ORDER BY form_title ASC'
        );

        $forms = [];

        foreach ($rows as $row) {
            $forms[(int) $row->form_id] = sprintf('%s (%d)', $row->form_title, (int) $row->total);
        }

        return $forms;
    }

    /** The whole lot as CSV, for one form or all of them. */
    public static function csv(int $form_id = 0): string
    {
        $rows    = self::all(['form_id' => $form_id, 'per_page' => 200, 'page' => 1]);
        $handle  = fopen('php://temp', 'r+');
        $columns = ['id', 'form', 'submitted_at', 'language', 'ip'];
        $labels  = [];

        // Two passes: the header cannot be written until every entry has been
        // seen, because forms change and older entries may carry fields the
        // newest ones no longer have.
        foreach ($rows as $row) {
            foreach (self::decode($row)['fields'] as $field) {
                $labels[$field['label']] = true;
            }
        }

        fputcsv($handle, array_merge($columns, array_keys($labels)));

        foreach ($rows as $row) {
            $entry = self::decode($row);
            $line  = [
                $entry['id'],
                $entry['form_title'],
                $entry['submitted_at'],
                $entry['language'],
                $entry['ip'],
            ];

            $byLabel = [];

            foreach ($entry['fields'] as $field) {
                $value = $field['value'];

                if ('checkbox' === $field['type']) {
                    $value = $value ? 'yes' : 'no';
                }

                $byLabel[$field['label']] = (string) $value;
            }

            foreach (array_keys($labels) as $label) {
                $line[] = $byLabel[$label] ?? '';
            }

            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
