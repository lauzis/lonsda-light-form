<?php

namespace LonsdaLightForm;

/**
 * Registers the lonsda/form block from its metadata.
 */
class Block
{
    public static function init(): void
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        // Registering from block.json keeps the metadata in one place; the
        // render file named there supplies the front-end markup.
        register_block_type(LLF_DIR . 'blocks/form');
    }
}
