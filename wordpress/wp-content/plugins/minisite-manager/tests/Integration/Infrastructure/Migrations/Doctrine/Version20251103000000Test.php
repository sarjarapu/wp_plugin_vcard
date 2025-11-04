<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Version20251103000000 migration
 * 
 * Tests that the migration creates the wp_minisite_config table correctly.
 * 
 * This migration creates:
 * - wp_minisite_config table (configuration key-value store)
 */
#[CoversClass(\Minisite\Infrastructure\Migrations\Doctrine\Version20251103000000::class)]
final class Version20251103000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return [
            'wp_minisite_config',
        ];
    }
    
    /**
     * Test that migration creates the minisite_config table
     */
    public function test_migrate_creates_minisite_config_table(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify table doesn't exist before migration
        $this->assertTableNotExists('wp_minisite_config', 'Table should not exist before migration');
        
        // Run migration
        $runner->migrate();
        
        // Verify table exists after migration
        $this->assertTableExists('wp_minisite_config', 'Table should exist after migration');
    }
    
    /**
     * Test that minisite_config table has correct schema
     */
    public function test_minisite_config_table_has_correct_schema(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify required columns exist
        $this->assertTableHasColumn('wp_minisite_config', 'id');
        $this->assertTableHasColumn('wp_minisite_config', 'config_key');
        $this->assertTableHasColumn('wp_minisite_config', 'config_value');
        $this->assertTableHasColumn('wp_minisite_config', 'config_type');
        $this->assertTableHasColumn('wp_minisite_config', 'description');
        $this->assertTableHasColumn('wp_minisite_config', 'is_sensitive');
        $this->assertTableHasColumn('wp_minisite_config', 'is_required');
        $this->assertTableHasColumn('wp_minisite_config', 'created_at');
        $this->assertTableHasColumn('wp_minisite_config', 'updated_at');
        
        // Verify column types
        $this->assertColumnType('wp_minisite_config', 'id', 'bigint');
        $this->assertColumnType('wp_minisite_config', 'config_key', 'varchar');
        // Note: MySQL converts 'text' to 'longtext' internally, so we check for either
        $columns = $this->getTableColumns('wp_minisite_config');
        $this->assertContains(
            strtolower($columns['config_value']['DATA_TYPE']),
            ['text', 'longtext'],
            'config_value should be text or longtext'
        );
    }
    
    /**
     * Test that table uses WordPress prefix correctly
     */
    public function test_table_uses_wordpress_prefix(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify table name includes prefix
        $this->assertTableExists('wp_minisite_config', 'Table should use wp_ prefix');
    }
    
    /**
     * Test that migration records the executed migration
     */
    public function test_migrate_records_executed_migration(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration
        $runner->migrate();
        
        // Verify migration is recorded in tracking table
        $this->assertMigrationExecuted('Version20251103000000');
    }
    
    /**
     * Test that running migration twice doesn't cause errors (idempotency)
     */
    public function test_migrate_is_idempotent(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration first time
        $runner->migrate();
        $this->assertTableExists('wp_minisite_config');
        
        // Run migration second time (should be safe - no new migrations)
        $runner->migrate();
        
        // Verify table still exists and wasn't corrupted
        $this->assertTableExists('wp_minisite_config', 'Table should still exist after second migration');
        
        // Verify schema is still correct
        $this->assertTableHasColumn('wp_minisite_config', 'id');
        $this->assertTableHasColumn('wp_minisite_config', 'config_key');
    }
    
    /**
     * Test that migration creates the migration tracking table
     */
    public function test_migrate_creates_migration_tracking_table(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Run migration
        $runner->migrate();
        
        // Verify migration tracking table exists
        $this->assertTableExists('wp_minisite_migrations', 'Migration tracking table should exist');
    }
    
    /**
     * Test that migration tracking table has correct schema
     */
    public function test_migration_tracking_table_has_correct_schema(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify required columns exist (Doctrine migrations tracking table)
        $this->assertTableHasColumn('wp_minisite_migrations', 'version');
        $this->assertTableHasColumn('wp_minisite_migrations', 'executed_at');
    }
    
    /**
     * Test that down() method drops the minisite_config table
     */
    public function test_down_drops_minisite_config_table(): void
    {
        // First run up() to create the table
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        $this->assertTableExists('wp_minisite_config', 'Table should exist after up()');
        
        // Get the migration instance and schema
        // AbstractMigration requires Connection and LoggerInterface
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251103000000($this->connection, $logger);
        $schemaManager = $this->connection->createSchemaManager();
        
        // Get current schema from database
        $currentSchema = $schemaManager->introspectSchema();
        
        // Create a new schema and apply down() to it
        $targetSchema = clone $currentSchema;
        $migration->down($targetSchema);
        
        // Calculate the diff and apply it
        $platform = $this->connection->getDatabasePlatform();
        $comparator = new \Doctrine\DBAL\Schema\Comparator($platform);
        $schemaDiff = $comparator->compareSchemas($currentSchema, $targetSchema);
        $schemaManager->alterSchema($schemaDiff);
        
        // Verify table is dropped
        $this->assertTableNotExists('wp_minisite_config', 'Table should not exist after down()');
    }
    
    /**
     * Test that down() is idempotent (can be run multiple times safely)
     */
    public function test_down_is_idempotent(): void
    {
        // First run up() to create the table
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        $this->assertTableExists('wp_minisite_config');
        
        // Get the migration instance
        // AbstractMigration requires Connection and LoggerInterface
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $migration = new \Minisite\Infrastructure\Migrations\Doctrine\Version20251103000000($this->connection, $logger);
        $schemaManager = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();
        $comparator = new \Doctrine\DBAL\Schema\Comparator($platform);
        
        // Run down() first time - should drop table
        $currentSchema1 = $schemaManager->introspectSchema();
        $targetSchema1 = clone $currentSchema1;
        $migration->down($targetSchema1);
        $schemaDiff1 = $comparator->compareSchemas($currentSchema1, $targetSchema1);
        $schemaManager->alterSchema($schemaDiff1);
        $this->assertTableNotExists('wp_minisite_config');
        
        // Run down() second time - should not error (table doesn't exist check)
        $currentSchema2 = $schemaManager->introspectSchema();
        $targetSchema2 = clone $currentSchema2;
        $migration->down($targetSchema2);
        $schemaDiff2 = $comparator->compareSchemas($currentSchema2, $targetSchema2);
        $schemaManager->alterSchema($schemaDiff2);
        $this->assertTableNotExists('wp_minisite_config', 'Table should still not exist after second down()');
    }
}

