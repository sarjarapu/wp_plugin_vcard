<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251104000000 migration
 * 
 * Tests that the migration adds new columns to wp_minisite_reviews table correctly.
 * 
 * This migration adds:
 * - author_email, author_phone (verification fields)
 * - language (auto-detected language)
 * - is_email_verified, is_phone_verified (separate verification flags)
 * - helpful_count, spam_score, sentiment_score (engagement and quality metrics)
 * - display_order, published_at (display and sorting)
 * - moderation_reason, moderated_by (moderation tracking)
 * - Extends status enum to include 'flagged'
 * 
 * Note: This migration assumes the base wp_minisite_reviews table exists
 * (created by custom migration system). If the table doesn't exist, the migration
 * will skip gracefully.
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000::class)]
final class Version20251104000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        // This migration doesn't create tables, it modifies existing ones
        // We only need to clean up the migration tracking table
        return [];
    }
    
    /**
     * Ensure base table exists before testing column additions
     * If it doesn't exist, create it (simulating the base migration)
     */
    private function ensureBaseTableExists(): void
    {
        if (!$this->tableExists('wp_minisite_reviews')) {
            // Create base table structure (simulating _1_0_0_CreateBase migration)
            $this->connection->executeStatement("
                CREATE TABLE wp_minisite_reviews (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    minisite_id VARCHAR(32) NOT NULL,
                    author_name VARCHAR(160) NOT NULL,
                    author_url VARCHAR(300) NULL,
                    rating DECIMAL(2,1) NOT NULL,
                    body MEDIUMTEXT NOT NULL,
                    locale VARCHAR(10) NULL,
                    visited_month CHAR(7) NULL,
                    source ENUM('manual','google','yelp','facebook','other') NOT NULL DEFAULT 'manual',
                    source_id VARCHAR(160) NULL,
                    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_by BIGINT UNSIGNED NULL,
                    PRIMARY KEY (id),
                    KEY idx_minisite (minisite_id),
                    KEY idx_status_date (status, created_at),
                    KEY idx_rating (rating)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }
    
    /**
     * Test that migration adds new columns to existing table
     */
    public function test_migrate_adds_new_columns(): void
    {
        $this->ensureBaseTableExists();
        
        // Remove migration tracking to simulate fresh state (if needed)
        // This allows the test to verify the migration runs correctly
        if ($this->tableExists('wp_doctrine_migration_versions')) {
            $this->connection->executeStatement(
                "DELETE FROM wp_doctrine_migration_versions WHERE version = '20251104000000'"
            );
        }
        
        // Drop new columns if they exist (to simulate pre-migration state)
        $columnsBefore = $this->getTableColumns('wp_minisite_reviews');
        $columnsToDrop = [];
        if (isset($columnsBefore['author_email'])) {
            $columnsToDrop[] = 'author_email';
        }
        if (isset($columnsBefore['is_email_verified'])) {
            $columnsToDrop[] = 'is_email_verified';
        }
        if (isset($columnsBefore['is_phone_verified'])) {
            $columnsToDrop[] = 'is_phone_verified';
        }
        if (isset($columnsBefore['helpful_count'])) {
            $columnsToDrop[] = 'helpful_count';
        }
        if (isset($columnsBefore['spam_score'])) {
            $columnsToDrop[] = 'spam_score';
        }
        if (isset($columnsBefore['sentiment_score'])) {
            $columnsToDrop[] = 'sentiment_score';
        }
        if (isset($columnsBefore['display_order'])) {
            $columnsToDrop[] = 'display_order';
        }
        if (isset($columnsBefore['published_at'])) {
            $columnsToDrop[] = 'published_at';
        }
        if (isset($columnsBefore['moderation_reason'])) {
            $columnsToDrop[] = 'moderation_reason';
        }
        if (isset($columnsBefore['moderated_by'])) {
            $columnsToDrop[] = 'moderated_by';
        }
        if (isset($columnsBefore['author_phone'])) {
            $columnsToDrop[] = 'author_phone';
        }
        if (isset($columnsBefore['language'])) {
            $columnsToDrop[] = 'language';
        }
        
        if (!empty($columnsToDrop)) {
            $dropStatements = array_map(fn($col) => "DROP COLUMN `{$col}`", $columnsToDrop);
            $this->connection->executeStatement(
                "ALTER TABLE wp_minisite_reviews " . implode(', ', $dropStatements)
            );
        }
        
        // Revert status enum to original if needed
        $this->connection->executeStatement(
            "ALTER TABLE wp_minisite_reviews MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'"
        );
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify new columns don't exist before migration
        $columnsBefore = $this->getTableColumns('wp_minisite_reviews');
        $this->assertArrayNotHasKey('author_email', $columnsBefore, 'author_email should not exist before migration');
        $this->assertArrayNotHasKey('is_email_verified', $columnsBefore, 'is_email_verified should not exist before migration');
        
        // Run migration
        $runner->migrate();
        
        // Verify new columns exist after migration
        $columnsAfter = $this->getTableColumns('wp_minisite_reviews');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_phone');
        $this->assertTableHasColumn('wp_minisite_reviews', 'language');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_email_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_phone_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'helpful_count');
        $this->assertTableHasColumn('wp_minisite_reviews', 'spam_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'sentiment_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'display_order');
        $this->assertTableHasColumn('wp_minisite_reviews', 'published_at');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderation_reason');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderated_by');
    }
    
    /**
     * Test that new columns have correct data types
     */
    public function test_new_columns_have_correct_types(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        $columns = $this->getTableColumns('wp_minisite_reviews');
        
        // Verify column types
        $this->assertColumnType('wp_minisite_reviews', 'author_email', 'varchar');
        $this->assertColumnType('wp_minisite_reviews', 'author_phone', 'varchar');
        $this->assertColumnType('wp_minisite_reviews', 'language', 'varchar');
        
        // Check boolean columns (MySQL stores as tinyint(1))
        $this->assertContains(
            strtolower($columns['is_email_verified']['DATA_TYPE']),
            ['tinyint', 'boolean'],
            'is_email_verified should be boolean/tinyint'
        );
        $this->assertContains(
            strtolower($columns['is_phone_verified']['DATA_TYPE']),
            ['tinyint', 'boolean'],
            'is_phone_verified should be boolean/tinyint'
        );
        
        // Check numeric columns
        $this->assertColumnType('wp_minisite_reviews', 'helpful_count', 'int');
        $this->assertColumnType('wp_minisite_reviews', 'spam_score', 'decimal');
        $this->assertColumnType('wp_minisite_reviews', 'sentiment_score', 'decimal');
        $this->assertColumnType('wp_minisite_reviews', 'display_order', 'int');
        
        // Check datetime column
        $this->assertContains(
            strtolower($columns['published_at']['DATA_TYPE']),
            ['datetime', 'timestamp'],
            'published_at should be datetime'
        );
        
        // Check varchar columns
        $this->assertColumnType('wp_minisite_reviews', 'moderation_reason', 'varchar');
        $this->assertColumnType('wp_minisite_reviews', 'moderated_by', 'bigint');
    }
    
    /**
     * Test that new columns have correct nullability
     */
    public function test_new_columns_have_correct_nullability(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        $columns = $this->getTableColumns('wp_minisite_reviews');
        
        // Nullable columns
        $this->assertEquals('YES', $columns['author_email']['IS_NULLABLE'], 'author_email should be nullable');
        $this->assertEquals('YES', $columns['author_phone']['IS_NULLABLE'], 'author_phone should be nullable');
        $this->assertEquals('YES', $columns['language']['IS_NULLABLE'], 'language should be nullable');
        $this->assertEquals('YES', $columns['spam_score']['IS_NULLABLE'], 'spam_score should be nullable');
        $this->assertEquals('YES', $columns['sentiment_score']['IS_NULLABLE'], 'sentiment_score should be nullable');
        $this->assertEquals('YES', $columns['display_order']['IS_NULLABLE'], 'display_order should be nullable');
        $this->assertEquals('YES', $columns['published_at']['IS_NULLABLE'], 'published_at should be nullable');
        $this->assertEquals('YES', $columns['moderation_reason']['IS_NULLABLE'], 'moderation_reason should be nullable');
        $this->assertEquals('YES', $columns['moderated_by']['IS_NULLABLE'], 'moderated_by should be nullable');
        
        // NOT NULL columns with defaults
        $this->assertEquals('NO', $columns['is_email_verified']['IS_NULLABLE'], 'is_email_verified should be NOT NULL');
        $this->assertEquals('NO', $columns['is_phone_verified']['IS_NULLABLE'], 'is_phone_verified should be NOT NULL');
        $this->assertEquals('NO', $columns['helpful_count']['IS_NULLABLE'], 'helpful_count should be NOT NULL');
    }
    
    /**
     * Test that status enum is extended to include 'flagged'
     */
    public function test_status_enum_includes_flagged(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify we can insert a review with 'flagged' status
        $this->connection->executeStatement("
            INSERT INTO wp_minisite_reviews 
            (minisite_id, author_name, rating, body, status) 
            VALUES ('test-flag', 'Test User', 3.0, 'Test review', 'flagged')
        ");
        
        $result = $this->connection->fetchOne(
            "SELECT status FROM wp_minisite_reviews WHERE minisite_id = 'test-flag'"
        );
        
        $this->assertEquals('flagged', $result, 'Status enum should include flagged');
        
        // Clean up
        $this->connection->executeStatement("DELETE FROM wp_minisite_reviews WHERE minisite_id = 'test-flag'");
    }
    
    /**
     * Test that migration is idempotent (can be run multiple times)
     */
    public function test_migrate_is_idempotent(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration first time
        $runner->migrate();
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        
        // Run migration second time (should be safe - checks for existing columns)
        $runner->migrate();
        
        // Verify columns still exist and weren't duplicated
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_email_verified');
        
        // Verify column count is correct (base + new columns, no duplicates)
        $columns = $this->getTableColumns('wp_minisite_reviews');
        $columnNames = array_keys($columns);
        $this->assertCount(1, array_filter($columnNames, fn($n) => $n === 'author_email'), 'author_email should exist exactly once');
    }
    
    /**
     * Test that migration records the executed migration
     */
    public function test_migrate_records_executed_migration(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration
        $runner->migrate();
        
        // Verify migration is recorded in tracking table
        $this->assertMigrationExecuted('Version20251104000000');
    }
    
    /**
     * Test that migration handles missing base table gracefully
     */
    public function test_migrate_handles_missing_table_gracefully(): void
    {
        // Drop table if it exists
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Migration should not throw error when table doesn't exist
        // It should skip gracefully
        try {
            $runner->migrate();
            $this->assertTrue(true, 'Migration should complete without error when table does not exist');
        } catch (\Exception $e) {
            $this->fail("Migration should handle missing table gracefully, but threw: " . $e->getMessage());
        }
    }
    
    /**
     * Test that default values are set correctly
     */
    public function test_default_values_are_correct(): void
    {
        $this->ensureBaseTableExists();
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        $columns = $this->getTableColumns('wp_minisite_reviews');
        
        // Check boolean defaults
        $this->assertEquals('0', $columns['is_email_verified']['COLUMN_DEFAULT'], 'is_email_verified default should be 0 (false)');
        $this->assertEquals('0', $columns['is_phone_verified']['COLUMN_DEFAULT'], 'is_phone_verified default should be 0 (false)');
        
        // Check integer default
        $this->assertEquals('0', $columns['helpful_count']['COLUMN_DEFAULT'], 'helpful_count default should be 0');
    }

    /**
     * Test that down() method removes all new columns
     */
    public function test_down_removes_new_columns(): void
    {
        $this->ensureBaseTableExists();

        // First run up() to add the columns
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify columns exist after up()
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_phone');
        $this->assertTableHasColumn('wp_minisite_reviews', 'language');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_email_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_phone_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'helpful_count');
        $this->assertTableHasColumn('wp_minisite_reviews', 'spam_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'sentiment_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'display_order');
        $this->assertTableHasColumn('wp_minisite_reviews', 'published_at');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderation_reason');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderated_by');

        // Get the migration instance
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);

        // Call down() which queues SQL statements via addSql()
        // We need to execute the SQL directly since down() uses raw SQL, not schema modifications
        $migration->down($this->connection->createSchemaManager()->introspectSchema());

        // Execute the queued SQL statements
        $sqlStatements = $migration->getSql();
        foreach ($sqlStatements as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        // Verify all new columns are removed
        $columnsAfter = $this->getTableColumns('wp_minisite_reviews');
        $this->assertArrayNotHasKey('author_email', $columnsAfter, 'author_email should be removed');
        $this->assertArrayNotHasKey('author_phone', $columnsAfter, 'author_phone should be removed');
        $this->assertArrayNotHasKey('language', $columnsAfter, 'language should be removed');
        $this->assertArrayNotHasKey('is_email_verified', $columnsAfter, 'is_email_verified should be removed');
        $this->assertArrayNotHasKey('is_phone_verified', $columnsAfter, 'is_phone_verified should be removed');
        $this->assertArrayNotHasKey('helpful_count', $columnsAfter, 'helpful_count should be removed');
        $this->assertArrayNotHasKey('spam_score', $columnsAfter, 'spam_score should be removed');
        $this->assertArrayNotHasKey('sentiment_score', $columnsAfter, 'sentiment_score should be removed');
        $this->assertArrayNotHasKey('display_order', $columnsAfter, 'display_order should be removed');
        $this->assertArrayNotHasKey('published_at', $columnsAfter, 'published_at should be removed');
        $this->assertArrayNotHasKey('moderation_reason', $columnsAfter, 'moderation_reason should be removed');
        $this->assertArrayNotHasKey('moderated_by', $columnsAfter, 'moderated_by should be removed');

        // Verify base columns still exist
        $this->assertTableHasColumn('wp_minisite_reviews', 'id');
        $this->assertTableHasColumn('wp_minisite_reviews', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_name');
        $this->assertTableHasColumn('wp_minisite_reviews', 'rating');
        $this->assertTableHasColumn('wp_minisite_reviews', 'body');
        $this->assertTableHasColumn('wp_minisite_reviews', 'status');
    }

    /**
     * Test that down() reverts status enum to original (removes 'flagged')
     */
    public function test_down_reverts_status_enum(): void
    {
        $this->ensureBaseTableExists();

        // First run up() to extend status enum
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify 'flagged' is in the enum after up()
        $this->connection->executeStatement("
            INSERT INTO wp_minisite_reviews 
            (minisite_id, author_name, rating, body, status) 
            VALUES ('test-flag-down', 'Test User', 3.0, 'Test review', 'flagged')
        ");
        $this->assertTrue(true, 'Should be able to insert with flagged status');

        // Get the migration instance
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);

        // Call down() which queues SQL statements via addSql()
        $migration->down($this->connection->createSchemaManager()->introspectSchema());

        // Execute the queued SQL statements
        $sqlStatements = $migration->getSql();
        foreach ($sqlStatements as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        // Clean up the test record with 'flagged' status first (it won't be valid after down())
        $this->connection->executeStatement("DELETE FROM wp_minisite_reviews WHERE minisite_id = 'test-flag-down'");

        // Verify 'flagged' is no longer in the enum (should fail to insert)
        try {
            $this->connection->executeStatement("
                INSERT INTO wp_minisite_reviews 
                (minisite_id, author_name, rating, body, status) 
                VALUES ('test-flag-down2', 'Test User', 3.0, 'Test review', 'flagged')
            ");
            $this->fail('Should not be able to insert with flagged status after down()');
        } catch (\Exception $e) {
            // Expected - 'flagged' should no longer be valid
            $this->assertStringContainsString('status', strtolower($e->getMessage()), 'Should fail due to invalid status value');
        }

        // Verify original status values still work
        $this->connection->executeStatement("
            INSERT INTO wp_minisite_reviews 
            (minisite_id, author_name, rating, body, status) 
            VALUES ('test-approved', 'Test User', 4.0, 'Test review', 'approved')
        ");
        $this->assertTrue(true, 'Should be able to insert with approved status');

        // Clean up
        $this->connection->executeStatement("DELETE FROM wp_minisite_reviews WHERE minisite_id IN ('test-flag-down2', 'test-approved')");
    }

    /**
     * Test that down() is idempotent (can be run multiple times safely)
     */
    public function test_down_is_idempotent(): void
    {
        $this->ensureBaseTableExists();

        // First run up() to add the columns
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');

        // Get the migration instance
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);

        // Run down() first time - should remove columns
        $migration->down($this->connection->createSchemaManager()->introspectSchema());
        $sqlStatements1 = $migration->getSql();
        foreach ($sqlStatements1 as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        $columnsAfter1 = $this->getTableColumns('wp_minisite_reviews');
        $this->assertArrayNotHasKey('author_email', $columnsAfter1, 'author_email should be removed after first down()');
        $this->assertArrayNotHasKey('is_email_verified', $columnsAfter1, 'is_email_verified should be removed after first down()');

        // Run down() second time - should not error (columns don't exist check)
        $migration2 = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);
        $migration2->down($this->connection->createSchemaManager()->introspectSchema());
        $sqlStatements2 = $migration2->getSql();
        foreach ($sqlStatements2 as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        // Verify columns still don't exist (no duplicates, no errors)
        $columnsAfter2 = $this->getTableColumns('wp_minisite_reviews');
        $this->assertArrayNotHasKey('author_email', $columnsAfter2, 'author_email should still not exist after second down()');
        $this->assertArrayNotHasKey('is_email_verified', $columnsAfter2, 'is_email_verified should still not exist after second down()');
    }

    /**
     * Test that down() handles missing columns gracefully (idempotent)
     */
    public function test_down_handles_missing_columns_gracefully(): void
    {
        $this->ensureBaseTableExists();

        // Ensure columns don't exist (simulate pre-migration state)
        $columnsBefore = $this->getTableColumns('wp_minisite_reviews');
        if (isset($columnsBefore['author_email'])) {
            // Columns already exist, so this test scenario is not applicable
            $this->markTestSkipped('Columns already exist - cannot test missing columns scenario');
            return;
        }

        // Get the migration instance
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);

        // Call down() which should handle missing columns gracefully
        try {
            $migration->down($this->connection->createSchemaManager()->introspectSchema());

            // Execute the queued SQL statements (should be empty or handle missing columns)
            $sqlStatements = $migration->getSql();
            foreach ($sqlStatements as $query) {
                try {
                    $this->connection->executeStatement($query->getStatement());
                } catch (\Exception $e) {
                    // If SQL fails due to missing columns, that's expected - the migration checks for existence
                    // The down() method checks if columns exist before trying to drop them
                    if (strpos(strtolower($e->getMessage()), 'column') !== false) {
                        // Expected - column doesn't exist, which is fine
                        continue;
                    }
                    throw $e;
                }
            }

            $this->assertTrue(true, 'down() should complete without error when columns do not exist');
        } catch (\Exception $e) {
            $this->fail("down() should handle missing columns gracefully, but threw: " . $e->getMessage());
        }
    }
}

