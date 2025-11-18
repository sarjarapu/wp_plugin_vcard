<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251109000000 migration
 *
 * This migration creates wp_minisite_payment_history table.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251109000000::class)]
final class Version20251109000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_payment_history'];
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_payment_history'), 'Table should be created');

        // Verify core columns
        $this->assertTableHasColumn('wp_minisite_payment_history', 'id');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'payment_id');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'action');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'amount');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'currency');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'payment_reference');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'expires_at');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'grace_period_ends_at');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'new_owner_user_id');
        $this->assertTableHasColumn('wp_minisite_payment_history', 'created_at');
    }

    /**
     * Test that action enum includes all expected values
     */
    public function test_action_enum_includes_all_values(): void
    {
        // Ensure table is created
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Create a test minisite for foreign key constraints
        $this->connection->executeStatement(
            "INSERT INTO wp_minisites (id, title, name, city, country_code, site_json) VALUES (?, ?, ?, ?, ?, ?)",
            array('test-history', 'Test Minisite', 'Test', 'Test City', 'US', '{}')
        );

        // Verify we can insert history records with all action values
        $actions = array(
            'initial_payment',
            'renewal',
            'expiration',
            'grace_period_start',
            'grace_period_end',
            'reclamation'
        );

        foreach ($actions as $action) {
            $this->connection->executeStatement(
                "INSERT INTO wp_minisite_payment_history
                (minisite_id, action)
                VALUES (?, ?)",
                array('test-history', $action)
            );

            $result = $this->connection->fetchOne(
                "SELECT action FROM wp_minisite_payment_history WHERE minisite_id = ? AND action = ?",
                array('test-history', $action)
            );

            $this->assertEquals($action, $result, "Action should include {$action}");

            // Clean up
            $this->connection->executeStatement(
                "DELETE FROM wp_minisite_payment_history WHERE minisite_id = ? AND action = ?",
                array('test-history', $action)
            );
        }

        // Clean up test data
        $this->connection->executeStatement("DELETE FROM wp_minisites WHERE id = 'test-history'");
    }

    /**
     * Test that foreign keys are created
     */
    public function test_migrate_creates_foreign_keys(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify foreign keys exist
        $foreignKeys = $this->getTableForeignKeys('wp_minisite_payment_history');
        $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

        $this->assertContains('fk_payment_history_minisite_id', $fkNames, 'Foreign key to minisites should exist');
        $this->assertContains('fk_payment_history_payment_id', $fkNames, 'Foreign key to payments should exist');
        $this->assertContains('fk_payment_history_new_owner_user_id', $fkNames, 'Foreign key to users should exist');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");

        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_payment_history'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists
        $this->assertTrue($this->tableExists('wp_minisite_payment_history'));
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251109000000');
    }
}

