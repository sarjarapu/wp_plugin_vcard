<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251104000000 migration
 * 
 * This migration creates wp_minisite_reviews table with all MVP fields (fresh start).
 * 
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000::class)]
final class Version20251104000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_reviews'];
    }
    
    /**
     * Test that migration creates table with all columns when table doesn't exist
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_reviews'), 'Table should be created');
        
        // Verify all MVP columns exist (24 fields)
        $this->assertTableHasColumn('wp_minisite_reviews', 'id');
        $this->assertTableHasColumn('wp_minisite_reviews', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_name');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_phone');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_url');
        $this->assertTableHasColumn('wp_minisite_reviews', 'rating');
        $this->assertTableHasColumn('wp_minisite_reviews', 'body');
        $this->assertTableHasColumn('wp_minisite_reviews', 'language');
        $this->assertTableHasColumn('wp_minisite_reviews', 'locale');
        $this->assertTableHasColumn('wp_minisite_reviews', 'visited_month');
        $this->assertTableHasColumn('wp_minisite_reviews', 'source');
        $this->assertTableHasColumn('wp_minisite_reviews', 'source_id');
        $this->assertTableHasColumn('wp_minisite_reviews', 'status');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_email_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_phone_verified');
        $this->assertTableHasColumn('wp_minisite_reviews', 'helpful_count');
        $this->assertTableHasColumn('wp_minisite_reviews', 'spam_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'sentiment_score');
        $this->assertTableHasColumn('wp_minisite_reviews', 'display_order');
        $this->assertTableHasColumn('wp_minisite_reviews', 'published_at');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderation_reason');
        $this->assertTableHasColumn('wp_minisite_reviews', 'moderated_by');
        $this->assertTableHasColumn('wp_minisite_reviews', 'created_at');
        $this->assertTableHasColumn('wp_minisite_reviews', 'updated_at');
        $this->assertTableHasColumn('wp_minisite_reviews', 'created_by');
    }
    
    /**
     * Test that status enum includes 'flagged'
     */
    public function test_status_enum_includes_flagged(): void
    {
        // Ensure table is created
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
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
        
        $this->assertEquals('flagged', $result, 'Status should include flagged');
        
        // Clean up
        $this->connection->executeStatement("DELETE FROM wp_minisite_reviews WHERE minisite_id = 'test-flag'");
    }
    
    /**
     * Test that migration is idempotent (can be run multiple times safely)
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration first time - should create table
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_reviews'), 'Table should be created after first run');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        
        // Run migration second time - should skip gracefully (table exists)
        $runner->migrate();
        
        // Verify table still exists and wasn't modified/damaged
        $this->assertTrue($this->tableExists('wp_minisite_reviews'), 'Table should still exist after second run');
        $this->assertTableHasColumn('wp_minisite_reviews', 'author_email');
        $this->assertTableHasColumn('wp_minisite_reviews', 'is_email_verified');
        
        // Verify column count is correct (no duplicates)
        $columns = $this->getTableColumns('wp_minisite_reviews');
        $columnNames = array_keys($columns);
        $this->assertCount(1, array_filter($columnNames, fn($n) => $n === 'author_email'), 'author_email should exist exactly once');
    }
    
    /**
     * Test that migration records the executed migration
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration
        $runner->migrate();
        
        // Verify migration is recorded in tracking table
        $this->assertMigrationExecuted('Version20251104000000');
    }

    /**
     * Test that down() method drops the table
     */
    public function test_down_drops_table(): void
    {
        // Ensure table is created first
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reviews");
        
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify table exists after up()
        $this->assertTrue($this->tableExists('wp_minisite_reviews'), 'Table should exist after up()');

        // Get the migration instance
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251104000000($this->connection, $logger);

        // Call down() which should drop the table
        $migration->down($this->connection->createSchemaManager()->introspectSchema());

        // Execute the queued SQL statements
        $sqlStatements = $migration->getSql();
        foreach ($sqlStatements as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        // Clear EntityManager state after dropping table to dispose of collections
        // This ensures any cached entities or collections referencing the dropped table are cleared
        $this->em->clear();

        // Verify table is dropped
        $this->assertFalse($this->tableExists('wp_minisite_reviews'), 'Table should be dropped after down()');
    }
}
