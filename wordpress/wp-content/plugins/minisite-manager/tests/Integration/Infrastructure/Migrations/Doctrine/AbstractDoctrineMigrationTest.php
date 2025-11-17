<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Tests\Integration\BaseIntegrationTest;

/**
 * Base class for Doctrine migration integration tests
 *
 * Provides common functionality:
 * - Database connection setup (inherited from BaseIntegrationTest)
 * - EntityManager creation (inherited from BaseIntegrationTest)
 * - WordPress $wpdb stub setup (inherited from BaseIntegrationTest)
 * - Helper methods for table/column verification
 * - Test cleanup (overrides BaseIntegrationTest to use getMigrationTables())
 *
 * Each migration should extend this class and test its specific migration.
 *
 * Note: PHPUnit will show a warning about this being abstract - this is expected
 * and harmless. Abstract base test classes are not executed as tests themselves.
 */
#[PHPUnit\Framework\Attributes\ExcludeFromClassCodeCoverage]
abstract class AbstractDoctrineMigrationTest extends BaseIntegrationTest
{
    protected \wpdb $wpdb;

    /**
     * Get the table names that this migration creates
     * Override this in child classes to specify which tables to clean up
     *
     * @return string[] Array of table names (with wp_ prefix)
     */
    abstract protected function getMigrationTables(): array;

    /**
     * Get entity paths for ORM configuration
     * Migration tests need access to all entities
     */
    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../src/Domain/Entities',
            __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',
            __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
            __DIR__ . '/../../../../src/Features/MinisiteManagement/Domain/Entities',
            __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
        );
    }

    /**
     * Setup test-specific services
     * Migration tests don't need services - they test migrations directly
     */
    protected function setupTestSpecificServices(): void
    {
        // No services needed for migration tests
    }

    /**
     * Clean up test-specific data
     * Migration tests clean up tables, not data
     */
    protected function cleanupTestData(): void
    {
        // No test data to clean - tables are dropped in cleanupTables()
    }

    protected function setUp(): void
    {
        // Call parent setUp() methods but skip migrations (we're testing migrations)
        // We need to replicate parent's setUp() but skip runMigrations() and setupTestSpecificServices()
        $this->initializeLogging();
        $this->defineConstants();
        $this->setupWordPressGlobals();
        $this->createDatabaseConnection();
        $this->createEntityManager();
        $this->resetConnectionState();
        $this->registerTablePrefixListener();

        // Set EntityManager in globals (needed for migrations that call ensureRepositoriesInitialized())
        $GLOBALS['minisite_entity_manager'] = $this->em;

        // Clear EntityManager's identity map
        $this->em->clear();

        // Clean up tables and create wp_users stub
        $this->cleanupTables();
        $this->createWordPressUsersTableStub();
        $this->createTestUser();

        // Store wpdb reference for convenience
        $this->wpdb = $GLOBALS['wpdb'];

        // NOTE: We intentionally skip:
        // - runMigrations() - migration tests run migrations manually
        // - setupTestSpecificServices() - migration tests don't need services
        // - cleanupTestData() - migration tests clean up tables, not data
    }

    /**
     * Clean up test tables (drop if they exist)
     * Overrides BaseIntegrationTest to drop ALL migration tables
     *
     * Note: Migration tests run ALL migrations (not just the one being tested),
     * so we need to clean up ALL migration tables to prevent duplicate data errors.
     * The getMigrationTables() method is still used for documentation purposes.
     */
    protected function cleanupTables(): void
    {
        // Find all tables with wp_minisite prefix that were created by migrations
        // This ensures we clean up ALL migration tables, not just the one being tested
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND (TABLE_NAME LIKE 'wp_minisite_%' OR TABLE_NAME = 'wp_minisites')",
            array($this->dbName)
        );

        // Always include the migration tracking table
        if (! in_array('wp_minisite_migrations', $tables, true)) {
            $tables[] = 'wp_minisite_migrations';
        }

        // Always include wp_users table (created for foreign key tests)
        $tables[] = 'wp_users';

        // Disable foreign key checks to allow dropping tables in any order
        // This is safe in test cleanup since we're dropping all tables anyway
        try {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                try {
                    $this->connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
                } catch (\Exception $e) {
                    // Ignore errors - table might not exist
                }
            }

            // Re-enable foreign key checks
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Exception $e) {
            // Ensure foreign key checks are re-enabled even if cleanup fails
            try {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up test tables after each test
        $this->cleanupTables();

        // Call parent tearDown() to clean up EntityManager and globals
        parent::tearDown();
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $tableName Table name (with or without prefix)
     * @return bool
     */
    protected function tableExists(string $tableName): bool
    {
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            array($this->dbName, $tableName)
        );

        return ! empty($tables);
    }

    /**
     * Get all columns for a table from INFORMATION_SCHEMA
     *
     * @param string $tableName Table name (with prefix)
     * @return array<string, array{COLUMN_NAME: string, DATA_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: mixed}>
     */
    protected function getTableColumns(string $tableName): array
    {
        $columns = $this->connection->fetchAllAssociative(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION",
            array($this->dbName, $tableName)
        );

        // Convert to keyed array by column name for easier checking
        $columnMap = array();
        foreach ($columns as $column) {
            $columnMap[$column['COLUMN_NAME']] = $column;
        }

        return $columnMap;
    }

    /**
     * Assert that a table exists in the database
     *
     * @param string $tableName Table name (with prefix)
     * @param string $message Optional assertion message
     */
    protected function assertTableExists(string $tableName, string $message = ''): void
    {
        $message = $message ?: "Table {$tableName} should exist";
        $this->assertTrue(
            $this->tableExists($tableName),
            $message
        );
    }

    /**
     * Assert that a table does not exist in the database
     *
     * @param string $tableName Table name (with prefix)
     * @param string $message Optional assertion message
     */
    protected function assertTableNotExists(string $tableName, string $message = ''): void
    {
        $message = $message ?: "Table {$tableName} should not exist";
        $this->assertFalse(
            $this->tableExists($tableName),
            $message
        );
    }

    /**
     * Assert that a table has a specific column
     *
     * @param string $tableName Table name (with prefix)
     * @param string $columnName Column name
     * @param string $message Optional assertion message
     */
    protected function assertTableHasColumn(string $tableName, string $columnName, string $message = ''): void
    {
        $columns = $this->getTableColumns($tableName);
        $message = $message ?: "Table {$tableName} should have column {$columnName}";
        $this->assertArrayHasKey($columnName, $columns, $message);
    }

    /**
     * Assert that a table column has a specific data type
     *
     * @param string $tableName Table name (with prefix)
     * @param string $columnName Column name
     * @param string $expectedType Expected MySQL data type (e.g., 'varchar', 'bigint', 'text')
     * @param string $message Optional assertion message
     */
    protected function assertColumnType(string $tableName, string $columnName, string $expectedType, string $message = ''): void
    {
        $columns = $this->getTableColumns($tableName);
        $this->assertArrayHasKey($columnName, $columns, "Column {$columnName} should exist");

        $actualType = strtolower($columns[$columnName]['DATA_TYPE']);
        $expectedType = strtolower($expectedType);

        $message = $message ?: "Column {$columnName} should be of type {$expectedType}, got {$actualType}";
        $this->assertEquals($expectedType, $actualType, $message);
    }

    /**
     * Get executed migrations from the tracking table
     *
     * @return array<array{version: string, executed_at: string}>
     */
    protected function getExecutedMigrations(): array
    {
        if (! $this->tableExists('wp_minisite_migrations')) {
            return array();
        }

        return $this->connection->fetchAllAssociative(
            "SELECT version, executed_at FROM wp_minisite_migrations ORDER BY executed_at"
        );
    }

    /**
     * Assert that a specific migration version has been executed
     *
     * @param string $version Version string (e.g., 'Version20251103000000')
     * @param string $message Optional assertion message
     */
    protected function assertMigrationExecuted(string $version, string $message = ''): void
    {
        $executedMigrations = $this->getExecutedMigrations();
        $message = $message ?: "Migration {$version} should be recorded as executed";

        $found = false;
        foreach ($executedMigrations as $migration) {
            if (str_contains($migration['version'], $version)) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, $message);
    }

    /**
     * Get all constraints for a table from INFORMATION_SCHEMA
     *
     * @param string $tableName Table name (with prefix)
     * @return array<array{CONSTRAINT_NAME: string, CONSTRAINT_TYPE: string}>
     */
    protected function getTableConstraints(string $tableName): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            array($this->dbName, $tableName)
        );
    }

    /**
     * Get all foreign keys for a table from INFORMATION_SCHEMA
     *
     * @param string $tableName Table name (with prefix)
     * @return array<array{CONSTRAINT_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string}>
     */
    protected function getTableForeignKeys(string $tableName): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ? AND kcu.REFERENCED_TABLE_NAME IS NOT NULL",
            array($this->dbName, $tableName)
        );
    }
}
