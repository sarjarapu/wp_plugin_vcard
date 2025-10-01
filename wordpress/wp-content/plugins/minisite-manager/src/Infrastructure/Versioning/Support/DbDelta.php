<?php

namespace Minisite\Infrastructure\Versioning\Support;

/**
 * Thin wrapper around WordPress dbDelta()
 * Ensures the file is loaded and returns any debug output as string array (ignored here).
 */
class DbDelta
{
    public static function run(string $createTableSql): void
    {
        self::ensureDbDeltaLoaded();
        // dbDelta returns array of messages; we don't need them here
        \dbDelta($createTableSql);
    }

    /**
     * Ensures the WordPress dbDelta function is loaded
     * This method can be mocked in tests to avoid file system dependencies
     */
    protected static function ensureDbDeltaLoaded(): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
    }
}
