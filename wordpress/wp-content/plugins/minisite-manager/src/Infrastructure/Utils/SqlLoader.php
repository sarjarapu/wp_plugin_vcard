<?php

namespace Minisite\Infrastructure\Utils;

use Minisite\Infrastructure\Versioning\Support\DbDelta;

class SqlLoader
{
    /**
     * Load SQL from file and execute it with variable replacement
     *
     * @param \wpdb|object $wpdb WordPress database instance or object with prefix and get_charset_collate method
     * @param string       $sqlFilePath Path to the SQL file relative to plugin root
     * @param array        $variables Variables to replace in the SQL (e.g., ['prefix' => 'wp_', 'charset' => 'utf8mb4_unicode_ci'])
     * @return void
     * @throws \InvalidArgumentException If SQL file doesn't exist
     * @throws \RuntimeException If SQL file cannot be read
     */
    public static function loadAndExecute($wpdb, string $sqlFilePath, array $variables = array()): void
    {
        // Reuse loadAndProcess to get the processed SQL
        $processedSql = self::loadAndProcess($sqlFilePath, $variables);

        // Execute using DbDelta
        DbDelta::run($processedSql);
    }

    /**
     * Load SQL from file and return processed SQL string without executing
     *
     * @param string $sqlFilePath Path to the SQL file relative to plugin root
     * @param array  $variables Variables to replace in the SQL
     * @return string Processed SQL string
     * @throws \InvalidArgumentException If SQL file doesn't exist
     * @throws \RuntimeException If SQL file cannot be read
     */
    public static function loadAndProcess(string $sqlFilePath, array $variables = array()): string
    {
        $fullPath = self::getFullPath($sqlFilePath);

        if (! file_exists($fullPath)) {
            throw new \InvalidArgumentException("SQL file not found: {$fullPath}");
        }

        $sql = file_get_contents($fullPath);
        if ($sql === false) {
            throw new \RuntimeException("Failed to read SQL file: {$fullPath}");
        }

        return self::replaceVariables($sql, $variables);
    }

    /**
     * Get the full path to a SQL file
     *
     * @param string $sqlFilePath Path relative to plugin root
     * @return string Full path to the SQL file
     */
    private static function getFullPath(string $sqlFilePath): string
    {
        // Get plugin root directory
        $pluginRoot = dirname(__DIR__, 3); // Go up from src/Infrastructure/Utils to plugin root

        // Ensure the path starts with data/db/tables/ if it's just a filename
        if (! str_starts_with($sqlFilePath, 'data/db/tables/')) {
            $sqlFilePath = 'data/db/tables/' . ltrim($sqlFilePath, '/');
        }

        return $pluginRoot . '/' . $sqlFilePath;
    }

    /**
     * Replace variables in SQL string
     *
     * @param string $sql Original SQL string
     * @param array  $variables Variables to replace
     * @return string SQL with variables replaced
     */
    private static function replaceVariables(string $sql, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $sql = str_replace('{$' . $key . '}', $value, $sql);
        }

        return $sql;
    }

    /**
     * Create standard variables for WordPress database operations
     *
     * @param \wpdb|object $wpdb WordPress database instance or object with prefix and get_charset_collate method
     * @return array Standard variables array
     */
    public static function createStandardVariables($wpdb): array
    {
        return array(
            'prefix'  => $wpdb->prefix,
            'charset' => $wpdb->get_charset_collate(),
        );
    }
}
