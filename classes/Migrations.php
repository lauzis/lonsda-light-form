<?php

namespace LonsdaLightForm;

/**
 * Schema changes, applied once each on upgrade.
 *
 * Registered against the plugin version that introduced the need for them; the
 * shared runner records what has been applied and never repeats it.
 */
class Migrations
{
    /** Bumped whenever the forms table changes shape. */
    public const TABLE = 'llf_forms';

    /** @return \Lauzis\WpPackages\Migrations\Runner|null */
    public static function runner()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        $runner = \WpPackages_Registry::migrations(LLF_SLUG, [
            'version' => LLF_VERSION,
            'option'  => 'llf_data_version',
        ]);

        $runner->add('0.2.0', [self::class, 'createFormsTable']);

        return $runner;
    }

    /** Applies anything outstanding. Safe on every request. */
    public static function run(): void
    {
        $runner = self::runner();

        if ($runner) {
            $runner->run();
        }
    }

    /**
     * Records the current version without running anything.
     *
     * On activation only: a fresh install has no old data, so the history is
     * not replayed. The table is created directly instead, since there is no
     * earlier version to migrate from.
     */
    public static function activate(): void
    {
        self::createFormsTable();

        $runner = self::runner();

        if ($runner) {
            $runner->baseline();
        }
    }

    /** Absolute table name, including the site's prefix. */
    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Creates the forms table.
     *
     * dbDelta() is used rather than a raw CREATE TABLE because it also brings
     * an existing table up to this definition, which is what makes this safe to
     * re-run and what will apply future column additions.
     */
    public static function createFormsTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::tableName();
        $collate = $wpdb->get_charset_collate();

        // dbDelta is particular: two spaces after PRIMARY KEY, one field per
        // line, and KEY names must match exactly or it will keep re-adding them.
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL DEFAULT 0,
                title varchar(255) NOT NULL DEFAULT '',
                settings longtext NOT NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                KEY post_id (post_id)
            ) {$collate};"
        );

        Logs::add('migration', 'Ensured the forms table exists.', ['table' => $table]);
    }
}
