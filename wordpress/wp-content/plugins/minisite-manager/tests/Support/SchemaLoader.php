<?php
namespace Tests\Support;

use PDO;

class SchemaLoader
{
    public static function rebuild(PDO $pdo, string $prefix = 'wp_'): void
    {
        $path = __DIR__ . '/sql/minisites.sql';
        if (!file_exists($path)) {
            throw new \RuntimeException('Schema SQL not found at ' . $path);
        }
        $sql = file_get_contents($path);
        $sql = str_replace('{{prefix}}', $prefix, $sql);

        // Split by semicolon to execute multiple statements; naive but fine for our controlled SQL
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }
    }
}
