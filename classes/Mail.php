<?php

namespace LonsdaLightForm;

/**
 * Sends an HTML message with a readable plain-text alternative.
 *
 * wp_mail() sends one body. Declaring it HTML and leaving it at that means a
 * client that shows plain text — or a mail setup that strips the HTML on the
 * way out — gets the markup flattened into a single unbroken paragraph, which
 * is how an auto reply arrives as a wall of text.
 *
 * So both parts go: the HTML as written, and a text version built from it with
 * the paragraph breaks preserved. PHPMailer sends them as multipart/alternative
 * and the client picks.
 */
class Mail
{
    /**
     * @param string|string[] $to
     * @param string          $subject
     * @param string          $html    Body, as markup.
     * @param string[]        $headers Anything else; the content type is added.
     * @return bool
     */
    public static function html($to, string $subject, string $html, array $headers = []): bool
    {
        $headers = self::withContentType($headers);
        $text    = self::toText($html);

        // Set on the PHPMailer instance rather than passed to wp_mail, which
        // has no argument for it. Attached for this send only: leaving it on
        // would put this message's text under somebody else's HTML.
        $alt = static function ($phpmailer) use ($text) {
            if ('' === (string) $phpmailer->AltBody) {
                $phpmailer->AltBody = $text;
            }
        };

        add_action('phpmailer_init', $alt);

        $sent = wp_mail($to, $subject, $html, $headers);

        remove_action('phpmailer_init', $alt);

        return (bool) $sent;
    }

    /**
     * A plain-text rendering of an HTML body.
     *
     * Block endings become blank lines and <br> a single one, so the text part
     * keeps the shape of the message rather than running it together. Links
     * carry their address, since a text reader cannot follow one otherwise.
     */
    public static function toText(string $html): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $html);

        // Whitespace between tags is layout, not content, and would otherwise
        // survive as stray spaces once the tags are gone.
        $text = preg_replace('/>\s+</', '><', $text) ?? $text;

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|h[1-6]|tr|blockquote)>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<hr\s*\/?>/i', "\n" . str_repeat('-', 32) . "\n", $text) ?? $text;
        $text = preg_replace('/<li[^>]*>/i', '- ', $text) ?? $text;
        $text = preg_replace('/<\/li>/i', "\n", $text) ?? $text;

        // "text (https://…)", because a plain-text reader has no link to click.
        $text = preg_replace_callback(
            '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            static function ($m) {
                $label = trim(wp_strip_all_tags($m[2]));
                $url   = trim($m[1]);

                return ('' === $label || $label === $url) ? $url : $label . ' (' . $url . ')';
            },
            $text
        ) ?? $text;

        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Trailing spaces before a break, then runs of blank lines: block tags
        // nest, so </p></div> would otherwise leave four newlines.
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param string[] $headers
     * @return string[]
     */
    private static function withContentType(array $headers): array
    {
        foreach ($headers as $header) {
            if (0 === stripos((string) $header, 'content-type:')) {
                return $headers;
            }
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return $headers;
    }
}
