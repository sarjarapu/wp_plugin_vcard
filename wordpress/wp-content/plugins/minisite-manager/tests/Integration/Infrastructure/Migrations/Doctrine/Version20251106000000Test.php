<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251106000000 migration
 *
 * This migration creates wp_minisites table for minisite management.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table from scratch
 * - If table exists, it was created by this migration, so skip gracefully
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251106000000::class)]
final class Version20251106000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisites'];
    }

    /**
     * Test that migration creates table with all columns
     */
    public function test_migrate_creates_table_with_all_columns(): void
    {
        // Note: We can't drop wp_minisites if other tables reference it via foreign keys
        // Instead, we rely on the migration's idempotency check

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify table exists
        $this->assertTrue($this->tableExists('wp_minisites'), 'Table should be created');

        // Verify core columns
        $this->assertTableHasColumn('wp_minisites', 'id');
        $this->assertTableHasColumn('wp_minisites', 'slug');
        $this->assertTableHasColumn('wp_minisites', 'business_slug');
        $this->assertTableHasColumn('wp_minisites', 'location_slug');
        $this->assertTableHasColumn('wp_minisites', 'title');
        $this->assertTableHasColumn('wp_minisites', 'name');
        $this->assertTableHasColumn('wp_minisites', 'city');
        $this->assertTableHasColumn('wp_minisites', 'region');
        $this->assertTableHasColumn('wp_minisites', 'country_code');
        $this->assertTableHasColumn('wp_minisites', 'postal_code');
        $this->assertTableHasColumn('wp_minisites', 'location_point');
        $this->assertTableHasColumn('wp_minisites', 'site_template');
        $this->assertTableHasColumn('wp_minisites', 'palette');
        $this->assertTableHasColumn('wp_minisites', 'industry');
        $this->assertTableHasColumn('wp_minisites', 'default_locale');
        $this->assertTableHasColumn('wp_minisites', 'schema_version');
        $this->assertTableHasColumn('wp_minisites', 'site_version');
        $this->assertTableHasColumn('wp_minisites', 'site_json');
        $this->assertTableHasColumn('wp_minisites', 'search_terms');
        $this->assertTableHasColumn('wp_minisites', 'status');
        $this->assertTableHasColumn('wp_minisites', 'publish_status');
        $this->assertTableHasColumn('wp_minisites', 'created_at');
        $this->assertTableHasColumn('wp_minisites', 'updated_at');
        $this->assertTableHasColumn('wp_minisites', 'published_at');
        $this->assertTableHasColumn('wp_minisites', 'created_by');
        $this->assertTableHasColumn('wp_minisites', 'updated_by');
        $this->assertTableHasColumn('wp_minisites', '_minisite_current_version_id');
    }

    /**
     * Test that unique constraints are created
     */
    public function test_migrate_creates_unique_constraints(): void
    {
        // Note: We can't drop wp_minisites if other tables reference it via foreign keys
        // Instead, we rely on the migration's idempotency check

        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify unique constraints exist
        $constraints = $this->getTableConstraints('wp_minisites');
        $constraintNames = array_column($constraints, 'CONSTRAINT_NAME');

        $this->assertContains('uniq_slug', $constraintNames, 'uniq_slug constraint should exist');
        $this->assertContains('uniq_business_location', $constraintNames, 'uniq_business_location constraint should exist');
    }

    /**
     * Test that migration is idempotent
     */
    public function test_migrate_is_idempotent(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);

        // Run migration first time
        $runner->migrate();
        $this->assertTrue($this->tableExists('wp_minisites'));

        // Run migration second time - should skip gracefully
        $runner->migrate();

        // Verify table still exists
        $this->assertTrue($this->tableExists('wp_minisites'));
    }

    /**
     * Test that migration records execution
     */
    public function test_migrate_records_executed_migration(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();

        // Verify migration is recorded
        $this->assertMigrationExecuted('Version20251106000000');
    }
}

