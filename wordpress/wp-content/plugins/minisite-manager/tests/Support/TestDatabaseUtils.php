<?php

namespace Tests\Support;

use PDO;
use Tests\Support\FakeWpdb;

/**
 * Database utilities for integration tests
 *
 * Provides common database connection and setup functionality for MySQL integration tests.
 */
class TestDatabaseUtils
{
    private PDO $pdo;
    private FakeWpdb $wpdb;

    public function __construct()
    {
        $this->connectToDatabase();
    }

    /**
     * Connect to the MySQL test database
     */
    private function connectToDatabase(): void
    {
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $db   = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        $this->pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->wpdb = new FakeWpdb($this->pdo);
    }

    /**
     * Get the PDO connection
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get the FakeWpdb instance
     */
    public function getWpdb(): FakeWpdb
    {
        return $this->wpdb;
    }

    /**
     * Clean up test tables
     */
    public function cleanupTestTables(): void
    {
        $tables = [
            'wp_minisites',
            'wp_minisite_versions',
            'wp_minisite_reviews',
            'wp_minisite_bookmarks',
            'wp_minisite_payments',
            'wp_minisite_payment_history',
            'wp_minisite_reservations',
            'wp_test_table'
        ];

        // First, drop foreign key constraints to avoid dependency issues
        $this->dropForeignKeyConstraints();

        // Then drop all tables
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
    }

    /**
     * Drop foreign key constraints to allow table cleanup
     */
    private function dropForeignKeyConstraints(): void
    {
        $constraints = [
            'wp_minisite_versions' => ['fk_versions_minisite_id'],
            'wp_minisite_reviews' => ['fk_reviews_minisite_id'],
            'wp_minisite_bookmarks' => ['fk_bookmarks_minisite_id'],
            'wp_minisite_payments' => ['fk_payments_minisite_id', 'fk_payments_user_id'],
            'wp_minisite_payment_history' => ['fk_payment_history_minisite_id', 'fk_payment_history_payment_id', 'fk_payment_history_new_owner_user_id'],
            'wp_minisite_reservations' => ['fk_reservations_user_id', 'fk_reservations_minisite_id'],
        ];

        foreach ($constraints as $table => $constraintNames) {
            foreach ($constraintNames as $constraintName) {
                try {
                    $this->pdo->exec("ALTER TABLE {$table} DROP FOREIGN KEY IF EXISTS {$constraintName}");
                } catch (\Exception $e) {
                    // Ignore errors - constraint might not exist
                }
            }
        }
    }

    /**
     * Create the minisites table using the actual schema
     */
    public function createMinisitesTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../data/db/tables/minisites.sql');
        $processedSql = str_replace(
            ['{$prefix}', '{$charset}'],
            ['wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'],
            $sql
        );
        $this->pdo->exec($processedSql);
    }

    /**
     * Create the minisite_versions table using the actual schema
     */
    public function createMinisiteVersionsTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../data/db/tables/minisite_versions.sql');
        $processedSql = str_replace(
            ['{$prefix}', '{$charset}'],
            ['wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'],
            $sql
        );
        $this->pdo->exec($processedSql);
    }

    /**
     * Create the minisite_reviews table using the actual schema
     */
    public function createMinisiteReviewsTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../data/db/tables/minisite_reviews.sql');
        $processedSql = str_replace(
            ['{$prefix}', '{$charset}'],
            ['wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'],
            $sql
        );
        $this->pdo->exec($processedSql);
    }

    /**
     * Create the minisite_reservations table using the actual schema
     */
    public function createMinisiteReservationsTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../data/db/tables/minisite_reservations.sql');
        $processedSql = str_replace(
            ['{$prefix}', '{$charset}'],
            ['wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'],
            $sql
        );
        $this->pdo->exec($processedSql);
    }

    /**
     * Create the minisite_bookmarks table using the actual schema
     */
    public function createMinisiteBookmarksTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../data/db/tables/minisite_bookmarks.sql');
        $processedSql = str_replace(
            ['{$prefix}', '{$charset}'],
            ['wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'],
            $sql
        );
        $this->pdo->exec($processedSql);
    }

    /**
     * Create all required tables for testing
     */
    public function createAllTables(): void
    {
        $this->createMinisitesTable();
        $this->createMinisiteVersionsTable();
        $this->createMinisiteReviewsTable();
        $this->createMinisiteReservationsTable();
        $this->createMinisiteBookmarksTable();
    }

    /**
     * Execute a SQL statement
     */
    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * Prepare and execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get a single value from a query
     */
    public function getVar(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Get a single row from a query
     */
    public function getRow(string $sql, array $params = []): array|false
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all rows from a query
     */
    public function getResults(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Static method to set up test database
     */
    public static function setUpTestDatabase(): void
    {
        $instance = new self();
        $instance->cleanupTestTables();
        $instance->createAllTables();
        
        // Set global wpdb for testing
        $GLOBALS['wpdb'] = $instance->getWpdb();
    }

    /**
     * Static method to tear down test database
     */
    public static function tearDownTestDatabase(): void
    {
        $instance = new self();
        $instance->cleanupTestTables();
        
        // Clean up global wpdb
        unset($GLOBALS['wpdb']);
    }
}
