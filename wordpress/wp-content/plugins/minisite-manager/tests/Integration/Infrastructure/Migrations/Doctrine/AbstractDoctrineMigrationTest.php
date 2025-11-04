<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;

/**
 * Base class for Doctrine migration integration tests
 * 
 * Provides common functionality:
 * - Database connection setup
 * - EntityManager creation
 * - WordPress $wpdb stub setup
 * - Helper methods for table/column verification
 * - Test cleanup
 * 
 * Each migration should extend this class and test its specific migration.
 * 
 * Note: PHPUnit will show a warning about this being abstract - this is expected
 * and harmless. Abstract base test classes are not executed as tests themselves.
 */
#[PHPUnit\Framework\Attributes\ExcludeFromClassCodeCoverage]
abstract class AbstractDoctrineMigrationTest extends TestCase
{
    protected \Doctrine\DBAL\Connection $connection;
    protected EntityManager $em;
    protected string $dbName;
    protected \wpdb $wpdb;
    
    /**
     * Get the table names that this migration creates
     * Override this in child classes to specify which tables to clean up
     * 
     * @return string[] Array of table names (with wp_ prefix)
     */
    abstract protected function getMigrationTables(): array;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get database configuration from environment
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';
        
        $this->dbName = $dbName;
        
        // Create real MySQL connection via Doctrine
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ]);
        
        // Create EntityManager with MySQL connection
        // In dev mode, Doctrine automatically uses ArrayCache if no cache is provided
        // We don't need to manually configure Symfony cache for tests
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../../../src/Domain/Entities'],
            isDevMode: true
        );
        
        $this->em = new EntityManager($this->connection, $config);
        
        // Set up $wpdb object (stub from bootstrap.php is fine - we only need the prefix)
        // Note: The wpdb stub doesn't actually connect to database - we use Doctrine for that.
        // Migrations only read $wpdb->prefix, they don't use wpdb for queries.
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $this->wpdb = $GLOBALS['wpdb'];
        $this->wpdb->prefix = 'wp_'; // Set the prefix that migrations will read
        
        // Clean up any existing test tables before running tests
        $this->cleanupTables();
        
        // Define WordPress database constants (for DoctrineFactory if needed)
        if (!defined('DB_HOST')) {
            define('DB_HOST', $host);
            define('DB_USER', $user);
            define('DB_PASSWORD', $pass);
            define('DB_NAME', $dbName);
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up test tables after each test
        $this->cleanupTables();
        
        $this->connection->close();
        $this->em->close();
        
        // Note: Don't unset $GLOBALS['wpdb'] - it's shared with other tests
        // The cleanupTables() already ran, so we're good
        
        parent::tearDown();
    }
    
    /**
     * Clean up test tables (drop if they exist)
     * Uses getMigrationTables() to determine which tables to clean
     */
    protected function cleanupTables(): void
    {
        $tables = $this->getMigrationTables();
        
        // Always include the migration tracking table
        $tables[] = 'wp_minisite_migrations';
        
        foreach ($tables as $table) {
            try {
                $this->connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            } catch (\Exception $e) {
                // Ignore errors - table might not exist
            }
        }
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
            [$this->dbName, $tableName]
        );
        
        return !empty($tables);
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
            [$this->dbName, $tableName]
        );
        
        // Convert to keyed array by column name for easier checking
        $columnMap = [];
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
        if (!$this->tableExists('wp_minisite_migrations')) {
            return [];
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
}

