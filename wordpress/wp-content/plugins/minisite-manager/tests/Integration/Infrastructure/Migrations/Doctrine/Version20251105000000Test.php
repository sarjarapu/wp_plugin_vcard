<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251105000000 migration
 *
 * This migration creates wp_minisite_versions table for version management.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251105000000::class)]
final class Version20251105000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_versions'];
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_versions");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisite_versions'), 'Table should be created');

        // Verify core versioning columns
        $this->assertTableHasColumn('wp_minisite_versions', 'id');
        $this->assertTableHasColumn('wp_minisite_versions', 'minisite_id');
        $this->assertTableHasColumn('wp_minisite_versions', 'version_number');
        $this->assertTableHasColumn('wp_minisite_versions', 'status');
        $this->assertTableHasColumn('wp_minisite_versions', 'label');
        $this->assertTableHasColumn('wp_minisite_versions', 'comment');
        $this->assertTableHasColumn('wp_minisite_versions', 'source_version_id');

        // Verify timestamp columns
        $this->assertTableHasColumn('wp_minisite_versions', 'created_at');
        $this->assertTableHasColumn('wp_minisite_versions', 'published_at');
        $this->assertTableHasColumn('wp_minisite_versions', 'created_by');

        // Verify minisite field columns
        $this->assertTableHasColumn('wp_minisite_versions', 'business_slug');
        $this->assertTableHasColumn('wp_minisite_versions', 'location_slug');
        $this->assertTableHasColumn('wp_minisite_versions', 'title');
        $this->assertTableHasColumn('wp_minisite_versions', 'name');
        $this->assertTableHasColumn('wp_minisite_versions', 'city');
        $this->assertTableHasColumn('wp_minisite_versions', 'region');
        $this->assertTableHasColumn('wp_minisite_versions', 'country_code');
        $this->assertTableHasColumn('wp_minisite_versions', 'postal_code');
        $this->assertTableHasColumn('wp_minisite_versions', 'location_point');
        $this->assertTableHasColumn('wp_minisite_versions', 'site_template');
        $this->assertTableHasColumn('wp_minisite_versions', 'palette');
        $this->assertTableHasColumn('wp_minisite_versions', 'industry');
        $this->assertTableHasColumn('wp_minisite_versions', 'default_locale');
        $this->assertTableHasColumn('wp_minisite_versions', 'schema_version');
        $this->assertTableHasColumn('wp_minisite_versions', 'site_version');
        $this->assertTableHasColumn('wp_minisite_versions', 'site_json');
        $this->assertTableHasColumn('wp_minisite_versions', 'search_terms');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_versions");

        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisite_versions'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists and wasn't modified
        $this->assertTrue($this->tableExists('wp_minisite_versions'));
        $this->assertTableHasColumn('wp_minisite_versions', 'id');
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        // Ensure table doesn't exist
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_versions");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251105000000');
    }

    /**
     * Test that down() method drops the table
     */
    public function test_down_drops_table(): void
    {
        // Ensure table is created first
        $this->connection->executeStatement("DROP TABLE IF EXISTS wp_minisite_versions");

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        $this->assertTrue($this->tableExists('wp_minisite_versions'));

        // Get migration instance and call down()
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251105000000($this->connection, $logger);

        $migration->down($this->connection->createSchemaManager()->introspectSchema());

        // Execute queued SQL
        $sqlStatements = $migration->getSql();
        foreach ($sqlStatements as $query) {
            $this->connection->executeStatement($query->getStatement());
        }

        $this->em->clear();

        // Verify table is dropped
        $this->assertFalse($this->tableExists('wp_minisite_versions'));
    }
}

