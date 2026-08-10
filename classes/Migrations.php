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
        $runner->add('0.7.0', [self::class, 'createEntriesTable']);
        // dbDelta adds the column to a table that already exists, so the same
        // definition serves both a new install and an upgrade.
        $runner->add('0.8.0', [self::class, 'createEntriesTable']);
        $runner->add('0.10.0', [self::class, 'reprojectForms']);
        // dbDelta adds the locale column to a table that already exists.
        $runner->add('0.12.0', [self::class, 'createEntriesTable']);
        // Definitions written before placeholders were translatable have no
        // key for them; rebuilding derives one from the field name.
        $runner->add('0.14.0', [self::class, 'reprojectForms']);
        // The submit button's key stopped being per form; stored definitions
        // still carry the old one until they are rebuilt.
        $runner->add('0.15.0', [self::class, 'reprojectForms']);
        // Gives every existing form a text id and the keys derived from it.
        $runner->add('0.17.0', [self::class, 'reprojectForms']);
        // And a key for the title, which is read out in every email the form
        // sends and until now went out in whatever language it was written in.
        $runner->add('0.24.0', [self::class, 'reprojectForms']);

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
        self::createEntriesTable();

        $runner = self::runner();

        if ($runner) {
            $runner->baseline();
        }
    }

    /**
     * Stored submissions.
     *
     * The answers are kept as JSON with each field's label and type alongside
     * its value, rather than as a reference to the form. A form's fields get
     * renamed and removed; an entry has to stay readable as what was actually
     * asked and answered at the time.
     */
    public static function createEntriesTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::entriesTableName();
        $collate = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                form_id bigint(20) unsigned NOT NULL DEFAULT 0,
                form_title varchar(255) NOT NULL DEFAULT '',
                post_id bigint(20) unsigned NULL DEFAULT NULL,
                language varchar(20) NOT NULL DEFAULT '',
                locale varchar(20) NOT NULL DEFAULT '',
                ip varchar(100) NOT NULL DEFAULT '',
                user_agent varchar(255) NOT NULL DEFAULT '',
                data longtext NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'new',
                submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                KEY form_id (form_id),
                KEY status (status),
                KEY submitted_at (submitted_at)
            ) {$collate};"
        );
    }

    /**
     * Rebuilds every form's stored definition from its post.
     *
     * Needed once because the projection used to bake in whether reCAPTCHA keys
     * existed at the moment a form was saved. A site that added its keys later
     * has forms recorded as not using reCAPTCHA even though the box is ticked,
     * and nothing short of re-saving each one would have corrected it.
     *
     * Harmless to run generally: the projection is derived from the post, so
     * rebuilding it can only bring it back in step.
     */
    public static function reprojectForms()
    {
        // Migrations run early and on any request. Returning false leaves the
        // version unrecorded so the runner tries again later, rather than
        // rebuilding every form from fields that are not registered yet.
        if (!FormBuilder::ready()) {
            return false;
        }

        $posts = get_posts([
            'post_type'      => Forms::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {
            Forms::syncToTable($post->ID, $post);
        }

        Logs::add('migration', 'Form definitions rebuilt.', ['forms' => count($posts)]);

        return true;
    }

    /** Absolute name of the entries table. */
    public static function entriesTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'llf_entries';
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
