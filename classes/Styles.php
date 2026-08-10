<?php

namespace LonsdaLightForm;

/**
 * The optional default stylesheet.
 *
 * The plugin shipped without any CSS at all, on the reasoning that a theme owns
 * how a form looks. That holds for the form itself and stops holding for the
 * parts a theme has never seen: the notice above the form and a field that came
 * back rejected exist only after a submission, and on most sites they arrived
 * unstyled — a rejected field indistinguishable from an accepted one, which is
 * the one moment the form has something to say.
 *
 * So there is a small stylesheet for those parts and a setting to turn it off.
 * Off means never enqueued rather than enqueued and overridden: a site that has
 * styled its own forms should not pay for a file it then has to undo.
 */
class Styles
{
    /** Handle, so a theme can dequeue it or register its own file under it. */
    public const HANDLE = 'llf-form';

    /** Setting id, as written in config/settings.json. */
    public const SETTING = 'styles';

    public static function init(): void
    {
        // Registered on the front end whether or not it will be used, so a
        // theme has something to dequeue or replace at the usual moment. The
        // enqueue happens later, when a form is actually rendered.
        add_action('wp_enqueue_scripts', [self::class, 'register']);
    }

    /** Whether the site wants them. On unless somebody turned them off. */
    public static function enabled(): bool
    {
        return (bool) Settings::get(self::SETTING, true);
    }

    public static function register(): void
    {
        wp_register_style(self::HANDLE, LLF_URL . 'assets/css/form.css', [], LLF_VERSION);
    }

    /** Called by the renderer, for a page that has a form on it. */
    public static function enqueue(): void
    {
        if (!self::enabled()) {
            return;
        }

        self::ensureRegistered();

        wp_enqueue_style(self::HANDLE);
    }

    /**
     * The same stylesheet, for the sample on the settings page.
     *
     * Enqueued whether or not the site uses them: the point of the sample is to
     * show what turning them on would do.
     */
    public static function enqueuePreview(): void
    {
        self::ensureRegistered();

        wp_enqueue_style(self::HANDLE);
    }

    /**
     * Registers late if the usual moment has already gone.
     *
     * A form can be rendered by something that runs before wp_enqueue_scripts —
     * a page builder resolving content early, a shortcode in a template part —
     * and an enqueue of a handle nobody registered is silently nothing.
     */
    private static function ensureRegistered(): void
    {
        if (!wp_style_is(self::HANDLE, 'registered')) {
            self::register();
        }
    }
}
