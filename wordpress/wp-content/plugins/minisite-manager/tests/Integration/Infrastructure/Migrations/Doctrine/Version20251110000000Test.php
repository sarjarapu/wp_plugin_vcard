<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251110000000 migration
 *
 * This migration creates wp_minisite_reservations table and MySQL purge event.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251110000000::class)]
final class Version20251110000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_reservations'];
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_reservations'), 'Table should be created');

        // Verify columns
        $this->assertTableHasColumn('wp_minisite_reservations', 'id');
        $this->assertTableHasColumn('wp_minisite_reservations', 'business_slug');
        $this->assertTableHasColumn('wp_minisite_reservations', 'location_slug');
        $this->assertTableHasColumn('wp_minisite_reservations', 'user_id');
        $this->assertTableHasColumn('wp_minisite_reservations', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_reservations', 'expires_at');
        $this->assertTableHasColumn('wp_minisite_reservations', 'created_at');
    }

    /**
     * Test that unique constraint is created
     */
    public function test_migrate_creates_unique_constraint(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify unique constraint exists
        $constraints = $this->getTableConstraints('wp_minisite_reservations');
        $constraintNames = array_column($constraints, 'CONSTRAINT_NAME');

        $this->assertContains('unique_slug_reservation', $constraintNames, 'unique_slug_reservation constraint should exist');
    }

    /**
     * Test that foreign keys are created
     */
    public function test_migrate_creates_foreign_keys(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify foreign keys exist
        $foreignKeys = $this->getTableForeignKeys('wp_minisite_reservations');
        $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

        $this->assertContains('fk_reservations_user_id', $fkNames, 'Foreign key to users should exist');
        $this->assertContains('fk_reservations_minisite_id', $fkNames, 'Foreign key to minisites should exist');
    }

    /**
     * Test that MySQL event is created
     */
    public function test_migrate_creates_mysql_event(): void
    {
        // Ensure table and event don't exist
        $this->connection->executeStatement("DROP EVENT IF EXISTS wp_minisite_purge_reservations_event");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify event exists
        $eventExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.EVENTS
             WHERE EVENT_SCHEMA = ? AND EVENT_NAME = ?",
            array($this->dbName, 'wp_minisite_purge_reservations_event')
        );

        $this->assertEquals(1, (int) $eventExists, 'MySQL event should be created');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure table and event don't exist
        $this->connection->executeStatement("DROP EVENT IF EXISTS wp_minisite_purge_reservations_event");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_reservations'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists
        $this->assertTrue($this->tableExists('wp_minisite_reservations'));

        // Verify event still exists
        $eventExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.EVENTS
             WHERE EVENT_SCHEMA = ? AND EVENT_NAME = ?",
            array($this->dbName, 'wp_minisite_purge_reservations_event')
        );

        $this->assertEquals(1, (int) $eventExists, 'MySQL event should still exist after second run');
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure table and event don't exist
        $this->connection->executeStatement("DROP EVENT IF EXISTS wp_minisite_purge_reservations_event");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_reservations");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251110000000');
    }
}

