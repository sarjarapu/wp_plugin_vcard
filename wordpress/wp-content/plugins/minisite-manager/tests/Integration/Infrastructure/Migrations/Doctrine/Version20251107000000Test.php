<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251107000000 migration
 *
 * This migration creates wp_minisite_bookmarks table.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251107000000::class)]
final class Version20251107000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_bookmarks'];
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure table doesn't exist (minisites table should already exist from earlier migrations)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_bookmarks");

        // Create minisites table first (required for foreign key)
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_bookmarks'), 'Table should be created');

        // Verify columns
        $this->assertTableHasColumn('wp_minisite_bookmarks', 'id');
        $this->assertTableHasColumn('wp_minisite_bookmarks', 'user_id');
        $this->assertTableHasColumn('wp_minisite_bookmarks', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_bookmarks', 'created_at');
    }

    /**
     * Test that unique constraint is created
     */
    public function test_migrate_creates_unique_constraint(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_bookmarks");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify unique constraint exists
        $constraints = $this->getTableConstraints('wp_minisite_bookmarks');
        $constraintNames = array_column($constraints, 'CONSTRAINT_NAME');

        $this->assertContains('uniq_user_minisite', $constraintNames, 'uniq_user_minisite constraint should exist');
    }

    /**
     * Test that foreign key is created
     */
    public function test_migrate_creates_foreign_key(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_bookmarks");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify foreign key exists
        $foreignKeys = $this->getTableForeignKeys('wp_minisite_bookmarks');
        $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

        $this->assertContains('fk_bookmarks_minisite_id', $fkNames, 'Foreign key should exist');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_bookmarks");

        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_bookmarks'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists
        $this->assertTrue($this->tableExists('wp_minisite_bookmarks'));
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_bookmarks");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251107000000');
    }
}

