<?php

namespace LonsdaLightForm;

/**
 * [lonsda_form id="1"] — renders a stored form in post content.
 */
class Shortcode
{
    public const TAG = 'lonsda_form';

    public static function init(): void
    {
        add_shortcode(self::TAG, [self::class, 'render']);
    }

    /**
     * @param array|string $atts
     */
    public static function render($atts = []): string
    {
        $atts = shortcode_atts(['id' => 0], (array) $atts, self::TAG);
        $id   = (int) $atts['id'];

        if ($id < 1) {
            return '';
        }

        // Carry the outcome back into the same form, so a rejected submission
        // redisplays with its errors and the visitor's answers intact.
        $result = Submission::result();
        $args   = ($result && (int) $result['form_id'] === $id)
            ? [
                'errors'  => $result['errors'],
                'values'  => $result['values'],
                'notice'  => $result['notice'],
                'success' => !empty($result['success']),
            ]
            : [];

        return Renderer::form($id, $args);
    }
}
