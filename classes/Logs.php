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
    public static function logger()
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

    /**
     * Whether the Logs screen is worth a menu entry.
     *
     * Not simply "is logging on": switching it off should not take away the
     * log it already wrote, which is usually the moment somebody wants to read
     * it. It goes when there is nothing left to read.
     */
    public static function hasSomethingToShow(): bool
    {
        $logger = self::logger();

        if (!$logger) {
            return false;
        }

        return $logger->isEnabled() || (bool) $logger->files();
    }

    /**
     * Deletes this plugin's log files.
     *
     * Recorded first, in the log being deleted: the line does not survive, but
     * PHP's error log gets it either way, which is where somebody wondering
     * what happened to a log will look.
     */
    public static function clear(): bool
    {
        $logger = self::logger();

        if (!$logger) {
            return false;
        }

        self::add('logs', 'Log cleared from the settings page.', ['user' => get_current_user_id()]);

        return (bool) $logger->clear();
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
