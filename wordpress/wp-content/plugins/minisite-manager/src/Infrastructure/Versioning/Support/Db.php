<?php

namespace Minisite\Infrastructure\Versioning\Support;

class Db
{
    public static function indexExists(\wpdb $wpdb, string $table, string $index): bool
    {
        $sql = $wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index);
        return (bool) $wpdb->get_var($sql);
    }

    public static function columnExists(\wpdb $wpdb, string $table, string $column): bool
    {
        $sql = $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column);
        return (bool) $wpdb->get_var($sql);
    }

    public static function tableExists(\wpdb $wpdb, string $table): bool
    {
        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        return (bool) $wpdb->get_var($sql);
    }
}
