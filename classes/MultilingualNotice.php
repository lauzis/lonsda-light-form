<?php

namespace LonsdaLightForm;

/**
 * Tells whoever is building a form how translation actually works here.
 *
 * There are two reasonable ways to run a form in several languages — translate
 * one form, or build one per language — and the plugin supports both without
 * preferring either. The moment to choose is while the first form is being
 * written: once the labels are typed, changing route means retyping every one
 * and orphaning any translation that pointed at the old wording.
 *
 * Shown only where a translation plugin is running. On a single-language site
 * none of this applies and the advice would be noise.
 */
class MultilingualNotice
{
    const NOTICE_ID = 'multilingual-forms';

    /** @return \Lauzis\WpPackages\Notices\Notices|null */
    private static function manager()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::notices(
            LLF_SLUG,
            [
                // Per user, and dismissed for good rather than per version:
                // this describes how the plugin works, not something that
                // changes with a release and needs saying again.
                'store'      => 'user',
                'capability' => 'manage_options',
                // The component otherwise only renders on pages whose ?page=
                // starts with the slug. This one belongs on the form editor,
                // which is post.php and has no page parameter at all.
                'screen'     => [self::class, 'onFormScreen'],
            ]
        );
    }

    public static function init(): void
    {
        $manager = self::manager();

        if (!$manager) {
            return;
        }

        $manager->boot();

        add_action('admin_notices', [self::class, 'maybeShow'], 5);
    }

    /** True when more than one language is in play on this site. */
    public static function isMultilingual(): bool
    {
        if (!class_exists('\Lauzis\WpPackages\I18n\Language')) {
            return false;
        }

        return \Lauzis\WpPackages\I18n\Language::is_multilingual();
    }

    /** Whether the screen being shown is a form being written or edited. */
    public static function onFormScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && Forms::POST_TYPE === $screen->post_type && 'post' === $screen->base;
    }

    public static function maybeShow(): void
    {
        $manager = self::manager();

        if (!$manager || !self::onFormScreen() || !self::isMultilingual()) {
            return;
        }

        $message = '<strong>'
            . esc_html__('This site runs in more than one language — two ways to handle that', 'lonsda-light-form')
            . '</strong><p>'
            . esc_html__('One form, translated: write the labels, placeholders and button in English, then translate them under Translations. All entries land in one list with the language recorded on each, and adding a field means adding it once.', 'lonsda-light-form')
            . '</p><p>'
            . esc_html__('Or a form per language: build one for each, written in that language, and ignore Translations entirely. Entries stay separated by form, and each can notify different people.', 'lonsda-light-form')
            . '</p><p>'
            . esc_html__('Either works, and the choice is per form. Worth deciding now: changing route later means retyping every label.', 'lonsda-light-form')
            . '</p><p><a href="' . esc_url(admin_url('admin.php?page=' . LLF_SLUG . '-help')) . '">'
            . esc_html__('Compare the two', 'lonsda-light-form')
            . '</a> &nbsp;·&nbsp; <a href="' . esc_url(admin_url('admin.php?page=' . LLF_SLUG . '-translations')) . '">'
            . esc_html__('Open Translations', 'lonsda-light-form')
            . '</a></p>';

        $manager->add(
            new \Lauzis\WpPackages\Notices\Notice(
                self::NOTICE_ID,
                $message,
                'info',
                \Lauzis\WpPackages\Notices\Notice::ONCE
            )
        );
    }
}
