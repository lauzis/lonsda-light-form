<?php

namespace LonsdaLightForm;

/**
 * Storage for form definitions.
 *
 * A form is edited through a hidden custom post type, because that is what
 * Carbon Fields can attach a repeatable editor to, and it brings the add/edit
 * screens, nonces and capability checks with it. The canonical runtime record
 * is the custom table: rendering a form should be one indexed row read, not a
 * post lookup plus a pile of meta queries.
 *
 * The post is therefore the editing surface and the table is the projection,
 * rewritten from it on every save.
 */
class Forms
{
    public const POST_TYPE = 'llf_form';

    /** Registers the post type and keeps the table in step with it. */
    public static function init(): void
    {
        add_action('init', [self::class, 'registerPostType']);
        add_action('rest_api_init', [self::class, 'registerRestFields']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'syncToTable'], 20, 2);
        add_action('before_delete_post', [self::class, 'removeFromTable']);
    }

    /**
     * Exposes the table id on the REST response.
     *
     * A form has two identifiers: the post it is edited as, and its row in the
     * forms table. The table id is the one the Forms list shows and the one the
     * shortcode takes, so it is the one a person is told to use — but the
     * editor lists forms over the post REST API, which knows only post ids.
     * Without this the block would quietly hand a post id to a lookup keyed by
     * the table id and find nothing.
     */
    public static function registerRestFields(): void
    {
        register_rest_field(self::POST_TYPE, 'llf_id', [
            'get_callback' => static function ($post) {
                return self::tableIdForPost((int) ($post['id'] ?? 0));
            },
            'schema' => [
                'description' => __('Identifier used by the shortcode and block.', 'lonsda-light-form'),
                'type'        => 'integer',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
        ]);
    }

    /** The forms-table id for a post, or 0 when it has not been projected yet. */
    public static function tableIdForPost(int $postId): int
    {
        global $wpdb;

        if ($postId < 1) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM ' . Migrations::tableName() . ' WHERE post_id = %d', $postId)
        );
    }

    public static function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Forms', 'lonsda-light-form'),
                'singular_name' => __('Form', 'lonsda-light-form'),
                'add_new_item'  => __('Add Form', 'lonsda-light-form'),
                'edit_item'     => __('Edit Form', 'lonsda-light-form'),
            ],
            // Not public: a form definition is configuration, not content, and
            // has no front-end URL of its own — it is rendered into a page.
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,   // surfaced under the plugin's own menu
            'exclude_from_search' => true,
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            // Exposed to REST so the block editor can list forms to choose
            // from. Not public: the endpoint still enforces capabilities, and
            // there is no front-end URL for a form definition.
            'show_in_rest'        => true,
            'rest_base'           => 'llf_form',
        ]);
    }

    /**
     * Writes the post's definition into the forms table.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     */
    public static function syncToTable($post_id, $post): void
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if ('auto-draft' === $post->post_status) {
            return;
        }

        // A definition read before Carbon Fields has registered its fields comes
        // back empty, and writing that over a good projection destroys the form
        // while leaving the post it was built from untouched — a failure that
        // looks like data loss and is not.
        if (!FormBuilder::ready()) {
            Logs::error('form', 'Refused to save a definition before Carbon Fields was ready.', [
                'post_id' => $post_id,
            ]);

            return;
        }

        global $wpdb;

        $table      = Migrations::tableName();
        $definition = FormBuilder::definition($post_id);
        $settings   = wp_json_encode($definition);

        // On save rather than on render: a translator should see a string
        // before anyone has visited the page it appears on.
        Strings::register($definition, (string) $post->post_title);

        $now      = current_time('mysql', true);
        $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE post_id = %d", $post_id));

        if ($existing) {
            $wpdb->update(
                $table,
                ['title' => $post->post_title, 'settings' => $settings, 'updated_at' => $now],
                ['id' => $existing],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'post_id'    => $post_id,
                    'title'      => $post->post_title,
                    'settings'   => $settings,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
        }

        Logs::add('form', 'Form definition saved.', [
            'post_id' => $post_id,
            'title'   => $post->post_title,
            'fields'  => count($definition['fields'] ?? []),
        ]);
    }

    /** Drops the row when its post is deleted, so the table cannot outlive it. */
    public static function removeFromTable($post_id): void
    {
        if (self::POST_TYPE !== get_post_type($post_id)) {
            return;
        }

        global $wpdb;

        $wpdb->delete(Migrations::tableName(), ['post_id' => (int) $post_id], ['%d']);

        Logs::add('form', 'Form definition deleted.', ['post_id' => $post_id]);
    }

    /**
     * Every stored form, newest first.
     *
     * @return object[]
     */
    public static function all(): array
    {
        global $wpdb;

        $table = Migrations::tableName();

        // The table may not exist yet on an install whose migration has not run.
        if ($table !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return [];
        }

        return (array) $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC");
    }

    /**
     * One form's decoded definition.
     *
     * @return array|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Migrations::tableName() . ' WHERE id = %d', $id));

        if (!$row) {
            return null;
        }

        $settings = json_decode((string) $row->settings, true);

        return [
            'id'       => (int) $row->id,
            'post_id'  => (int) $row->post_id,
            'title'    => (string) $row->title,
            'settings' => is_array($settings) ? $settings : [],
        ];
    }
}
