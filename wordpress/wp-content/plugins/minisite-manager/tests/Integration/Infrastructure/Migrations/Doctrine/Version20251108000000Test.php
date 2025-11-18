<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251108000000 migration
 *
 * This migration creates wp_minisite_payments table.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251108000000::class)]
final class Version20251108000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return array('wp_minisite_payments');
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure tables don't exist (drop in order to handle foreign keys)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payments");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_payments'), 'Table should be created');

        // Verify core columns
        $this->assertTableHasColumn('wp_minisite_payments', 'id');
        $this->assertTableHasColumn('wp_minisite_payments', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_payments', 'user_id');
        $this->assertTableHasColumn('wp_minisite_payments', 'woocommerce_order_id');
        $this->assertTableHasColumn('wp_minisite_payments', 'status');
        $this->assertTableHasColumn('wp_minisite_payments', 'amount');
        $this->assertTableHasColumn('wp_minisite_payments', 'currency');
        $this->assertTableHasColumn('wp_minisite_payments', 'payment_method');
        $this->assertTableHasColumn('wp_minisite_payments', 'payment_reference');
        $this->assertTableHasColumn('wp_minisite_payments', 'paid_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'expires_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'grace_period_ends_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'renewed_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'reclaimed_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'created_at');
        $this->assertTableHasColumn('wp_minisite_payments', 'updated_at');
    }

    /**
     * Test that status enum includes all expected values
     */
    public function test_status_enum_includes_all_values(): void
    {
        // Ensure tables don't exist (drop in order to handle foreign keys)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payments");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Create a test minisite and user for foreign key constraints
        $this->connection->executeStatement(
            "INSERT INTO wp_minisites (id, title, name, city, country_code, site_json) VALUES (?, ?, ?, ?, ?, ?)",
            array('test-payment', 'Test Minisite', 'Test', 'Test City', 'US', '{}')
        );
        // User with ID=1 is already created by BaseIntegrationTest::createTestUser()
        // Just ensure it exists (it should already exist from setUp())
        // No need to insert - it's already there

        // Verify we can insert payments with all status values
        $statuses = array('active', 'expired', 'grace_period', 'reclaimed');

        foreach ($statuses as $status) {
            $this->connection->executeStatement(
                "INSERT INTO wp_minisite_payments
                (minisite_id, user_id, amount, paid_at, expires_at, grace_period_ends_at, status)
                VALUES (?, ?, 10.00, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), DATE_ADD(NOW(), INTERVAL 1 YEAR), ?)",
                array('test-payment', 1, $status)
            );

            $result = $this->connection->fetchOne(
                "SELECT status FROM wp_minisite_payments WHERE minisite_id = ? AND status = ?",
                array('test-payment', $status)
            );

            $this->assertEquals($status, $result, "Status should include {$status}");

            // Clean up
            $this->connection->executeStatement(
                "DELETE FROM wp_minisite_payments WHERE minisite_id = ? AND status = ?",
                array('test-payment', $status)
            );
        }

        // Clean up test data
        $this->connection->executeStatement("DELETE FROM wp_minisites WHERE id = 'test-payment'");
        // Note: Don't delete wp_users ID=1 - it's created by BaseIntegrationTest::createTestUser()
        // and is needed for other tests
    }

    /**
     * Test that foreign keys are created
     */
    public function test_migrate_creates_foreign_keys(): void
    {
        // Ensure tables don't exist (drop in order to handle foreign keys)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payments");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify foreign keys exist
        $foreignKeys = $this->getTableForeignKeys('wp_minisite_payments');
        $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

        $this->assertContains('fk_payments_minisite_id', $fkNames, 'Foreign key to minisites should exist');
        $this->assertContains('fk_payments_user_id', $fkNames, 'Foreign key to users should exist');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure tables don't exist (drop in order to handle foreign keys)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payments");

        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_payments'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists
        $this->assertTrue($this->tableExists('wp_minisite_payments'));
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure tables don't exist (drop in order to handle foreign keys)
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payment_history");
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_payments");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251108000000');
    }
}
