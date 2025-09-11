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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // dbDelta returns array of messages; we don't need them here
        \dbDelta($createTableSql);
    }
}
