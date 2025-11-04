<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for DoctrineMigrationRunner
 * 
 * Tests the orchestration capabilities of the runner:
 * - Migration discovery
 * - Execution state tracking
 * - Idempotency
 * - Error handling
 * - WordPress prefix integration
 * 
 * Note: These tests focus on orchestration, NOT individual migration logic.
 * Individual migrations are tested in their respective test files.
 * 
 * IMPORTANT: This test discovers and executes ALL migration files in
 * src/Infrastructure/Migrations/Doctrine/ directory. If you have 10 migration
 * files, all 10 will be executed during these tests.
 * 
 * When adding new migrations:
 * 1. Add their table names to getMigrationTables() below
 * 2. Tests will automatically discover and execute them
 * 3. Ensure migrations are ordered correctly (by version/timestamp)
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner::class)]
final class DoctrineMigrationRunnerIntegrationTest extends AbstractDoctrineMigrationTest
{
    /**
     * Return all tables created by migrations in this directory
     * 
     * When you add new migrations, add their table names here so cleanup works correctly.
     * Example: If Version20251104000000 creates wp_users_table, add it here.
     * 
     * @return string[] Array of table names (with wp_ prefix)
     */
    protected function getMigrationTables(): array
    {
        return [
            'wp_minisite_config',
            // Add future migration tables here:
            // 'wp_some_other_table',
            // 'wp_another_table',
        ];
    }
    
    /**
     * Test that migrate() discovers and executes pending migrations
     * 
     * Note: This test will execute ALL pending migrations in the directory.
     * If you have 10 migration files, all 10 will be discovered and executed.
     */
    public function test_migrate_discovers_and_executes_pending_migrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify no migrations executed yet
        $this->assertEmpty($this->getExecutedMigrations(), 'No migrations should be executed initially');
        
        // Verify table doesn't exist before migration
        $this->assertTableNotExists('wp_minisite_config', 'Table should not exist before migration');
        
        // Run migrations - this will execute ALL pending migrations in the directory
        $runner->migrate();
        
        // Verify at least one migration was executed and tracked
        $executedMigrations = $this->getExecutedMigrations();
        $this->assertGreaterThanOrEqual(1, count($executedMigrations), 'At least one migration should be executed');
        
        // Verify Version20251103000000 was executed (the first one)
        $this->assertMigrationExecuted('Version20251103000000', 'Version20251103000000 should be tracked as executed');
        
        // Verify table was created (orchestration worked - migration was executed)
        $this->assertTableExists('wp_minisite_config', 'Table should exist after migration execution');
        
        // Note: If there are more migrations, they will also be executed and tracked
        // All migrations are discovered via GlobFinder and executed in version order
    }
    
    /**
     * Test that migrate() skips already-executed migrations
     */
    public function test_migrate_skips_already_executed_migrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // First run - execute migrations
        $runner->migrate();
        $firstRunMigrations = $this->getExecutedMigrations();
        $firstRunCount = count($firstRunMigrations);
        
        $this->assertGreaterThan(0, $firstRunCount, 'At least one migration should be executed on first run');
        
        // Second run - should skip already-executed migrations
        $runner->migrate();
        $secondRunMigrations = $this->getExecutedMigrations();
        $secondRunCount = count($secondRunMigrations);
        
        // Should be same count (no new migrations executed)
        $this->assertEquals(
            $firstRunCount,
            $secondRunCount,
            'Second run should not execute already-executed migrations'
        );
        
        // Verify table still exists and wasn't corrupted
        $this->assertTableExists('wp_minisite_config', 'Table should still exist after second run');
    }
    
    /**
     * Test that migrate() is idempotent (safe to run multiple times)
     */
    public function test_migrate_is_idempotent(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migrations multiple times
        $runner->migrate();
        $runner->migrate();
        $runner->migrate();
        
        // Should not error and state should be consistent
        $executedMigrations = $this->getExecutedMigrations();
        $this->assertGreaterThanOrEqual(
            1,
            count($executedMigrations),
            'At least one migration should be tracked'
        );
        
        // Verify table exists and is intact
        $this->assertTableExists('wp_minisite_config', 'Table should exist after multiple runs');
        $this->assertTableHasColumn('wp_minisite_config', 'id', 'Table structure should be intact');
    }
    
    /**
     * Test that migrate() creates metadata storage table
     */
    public function test_migrate_creates_metadata_storage_table(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify metadata table doesn't exist initially
        $this->assertTableNotExists('wp_minisite_migrations', 'Metadata table should not exist initially');
        
        // Run migrations - this should create metadata storage table
        $runner->migrate();
        
        // Verify metadata table was created
        $this->assertTableExists('wp_minisite_migrations', 'Metadata storage table should be created');
        
        // Verify table has correct schema
        $this->assertTableHasColumn('wp_minisite_migrations', 'version', 'Metadata table should have version column');
        $this->assertTableHasColumn('wp_minisite_migrations', 'executed_at', 'Metadata table should have executed_at column');
    }
    
    /**
     * Test that migrate() uses WordPress table prefix correctly
     */
    public function test_migrate_uses_wordpress_table_prefix(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify wpdb prefix is set
        $this->assertEquals('wp_', $GLOBALS['wpdb']->prefix, 'WordPress prefix should be wp_');
        
        // Run migrations
        $runner->migrate();
        
        // Verify metadata table uses wp_ prefix
        $this->assertTableExists('wp_minisite_migrations', 'Metadata table should use wp_ prefix');
        
        // Verify migration table uses wp_ prefix
        $this->assertTableExists('wp_minisite_config', 'Migration table should use wp_ prefix');
    }
    
    /**
     * Test that migrate() tracks executed migrations correctly
     */
    public function test_migrate_tracks_executed_migrations_correctly(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migrations
        $runner->migrate();
        
        // Get executed migrations
        $executedMigrations = $this->getExecutedMigrations();
        
        // Should have at least one migration tracked
        $this->assertGreaterThanOrEqual(1, count($executedMigrations), 'At least one migration should be tracked');
        
        // Verify Version20251103000000 is in the list
        $found = false;
        foreach ($executedMigrations as $migration) {
            if (str_contains($migration['version'], 'Version20251103000000')) {
                $found = true;
                $this->assertNotEmpty($migration['executed_at'], 'Executed timestamp should be set');
                break;
            }
        }
        
        $this->assertTrue($found, 'Version20251103000000 should be in executed migrations list');
    }
    
    /**
     * Test that migrate() with injected EntityManager uses the provided instance
     */
    public function test_migrate_with_injected_entity_manager(): void
    {
        // Create runner with injected EntityManager
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migrations
        $runner->migrate();
        
        // Verify it worked (uses injected EntityManager)
        $this->assertTableExists('wp_minisite_config', 'Migration should work with injected EntityManager');
        $this->assertMigrationExecuted('Version20251103000000', 'Migration should be tracked');
    }
    
    /**
     * Test that getEntityManager() uses injected EntityManager
     * 
     * This tests the getEntityManager() method via reflection to ensure
     * it returns the injected EntityManager rather than creating a new one.
     */
    public function test_getEntityManager_uses_injected_instance(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('getEntityManager');
        $method->setAccessible(true);
        
        $result = $method->invoke($runner);
        
        // Should return the same EntityManager instance we injected
        $this->assertSame($this->em, $result);
    }
    
    /**
     * Test that migrate() handles case where all migrations are already executed
     */
    public function test_migrate_handles_all_migrations_already_executed(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // First run - execute migrations
        $runner->migrate();
        $firstRunCount = count($this->getExecutedMigrations());
        
        // Second run - all already executed
        $runner->migrate();
        $secondRunCount = count($this->getExecutedMigrations());
        
        // Should be same count, no errors thrown
        $this->assertEquals($firstRunCount, $secondRunCount, 'Count should remain same when all migrations executed');
        
        // Verify table still exists
        $this->assertTableExists('wp_minisite_config', 'Table should still exist');
    }
    
    /**
     * Test that migrate() executes migrations in correct order
     * 
     * Note: With only one migration, we can't fully test ordering,
     * but we verify that the migration system is working correctly.
     * When multiple migrations exist, Doctrine should execute them in version order.
     */
    public function test_migrate_executes_migrations_in_order(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migrations
        $runner->migrate();
        
        // Verify migration was executed
        $this->assertMigrationExecuted('Version20251103000000', 'Migration should be executed');
        
        // With current setup (one migration), order is not testable
        // But we verify the system is working - when more migrations are added,
        // Doctrine will execute them in version order (earliest first)
        $this->assertTableExists('wp_minisite_config', 'Table should exist, indicating migration executed');
    }
    
    /**
     * Test that executePendingMigrations() handles "no pending migrations" path
     * 
     * This tests the else branch in executePendingMigrations() when
     * count($availableMigrations) === 0
     */
    public function test_executePendingMigrations_handles_no_pending_migrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // First run - execute migrations
        $runner->migrate();
        
        // Second run - all migrations already executed, should hit "no pending migrations" path
        $runner->migrate();
        
        // Verify no errors occurred and state is consistent
        $executedMigrations = $this->getExecutedMigrations();
        $this->assertGreaterThanOrEqual(1, count($executedMigrations), 'Migrations should still be tracked');
        
        // Verify table still exists
        $this->assertTableExists('wp_minisite_config', 'Table should still exist');
    }
    
    
    /**
     * Test that createMigrationConfiguration() includes WordPress prefix
     * 
     * This tests that the configuration correctly uses the WordPress table prefix.
     */
    public function test_createMigrationConfiguration_includes_wordpress_prefix(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        $reflection = new \ReflectionClass($runner);
        $method = $reflection->getMethod('createMigrationConfiguration');
        $method->setAccessible(true);
        
        $config = $method->invoke($runner);
        
        // Verify config is created
        $this->assertInstanceOf(\Doctrine\Migrations\Configuration\Migration\ConfigurationArray::class, $config);
        
        // The prefix is used in the table_storage configuration
        // We verify this indirectly by checking migrations work correctly
        $runner->migrate();
        $this->assertTableExists('wp_minisite_migrations', 'Metadata table should use wp_ prefix');
    }
    
    /**
     * Test that runMigrations() executes migrations successfully
     * 
     * This tests the runMigrations() method indirectly by verifying
     * that migrations are executed and tracked correctly.
     */
    public function test_runMigrations_executes_migrations_successfully(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migrations - this calls runMigrations() internally
        $runner->migrate();
        
        // Verify migrations were executed
        $this->assertMigrationExecuted('Version20251103000000', 'Migration should be executed');
        $this->assertTableExists('wp_minisite_config', 'Table should exist after migration');
    }
}

