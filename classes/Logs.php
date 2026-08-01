<?php

namespace LonsdaLightForm;

/**
 * Lonsda Light Form's logging entry point.
 *
 * A thin facade over the shared logger so call sites stay short and the plugin
 * degrades to silence rather than fataling when the package is absent.
 */
class Logs
{
    /** @return \Lauzis\WpPackages\Logs\Logger|null */
    private static function logger()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        // No 'enabled' passed: the component reads logs_enabled from the schema
        // registered in Settings.
        return \WpPackages_Registry::logger(LLF_SLUG, ['dir' => LLF_LOG_PATH]);
    }

    public static function add(string $action, string $message = '', array $context = []): bool
    {
        $logger = self::logger();

        return $logger ? $logger->add($action, $message, $context) : false;
    }

    /** Always reaches PHP's error log, whatever the logging setting says. */
    public static function error(string $action, string $message = '', array $context = []): void
    {
        $logger = self::logger();

        if ($logger) {
            $logger->error($action, $message, $context);
        }
    }
}
