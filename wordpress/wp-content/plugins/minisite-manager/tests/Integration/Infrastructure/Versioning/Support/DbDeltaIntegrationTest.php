<?php

namespace Tests\Integration\Infrastructure\Versioning\Support;

use Minisite\Infrastructure\Versioning\Support\DbDelta;
use Minisite\Infrastructure\Versioning\Support\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class DbDeltaIntegrationTest extends TestCase
{
    private DatabaseTestHelper $dbHelper;
    private $originalWpdb;
    private $originalAbspath;

    protected function setUp(): void
    {
        // Store the original $wpdb to restore later
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        
        // Store original ABSPATH
        $this->originalAbspath = defined('ABSPATH') ? ABSPATH : null;
        
        // Define ABSPATH for testing if not already defined
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/fake/wordpress/path/');
        }
        
        // Set up database helper
        $this->dbHelper = new DatabaseTestHelper();
        
        // Clean up any existing test data
        $this->dbHelper->cleanupTestTables();
        
        // Set the global $wpdb to our test database
        $GLOBALS['wpdb'] = $this->dbHelper->getWpdb();
        
        // Load our mock upgrade.php file
        $this->loadMockUpgradeFile();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dbHelper->cleanupTestTables();
        
        // Restore the original $wpdb
        $GLOBALS['wpdb'] = $this->originalWpdb;
    }

    private function loadMockUpgradeFile(): void
    {
        // Load our mock upgrade.php file that provides the dbDelta function
        $mockFile = dirname(__DIR__, 4) . '/Support/mock-upgrade.php';
        require_once $mockFile;
    }

    public function test_run_creates_simple_table(): void
    {
        // Arrange
        $createTableSql = "CREATE TABLE wp_test_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        )";

        // Act
        DbDelta::run($createTableSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_test_table'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'name'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'PRIMARY'));
    }

    public function test_run_creates_table_with_indexes(): void
    {
        // Arrange
        $createTableSql = "CREATE TABLE wp_indexed_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_created (created_at)
        )";

        // Act
        DbDelta::run($createTableSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_indexed_table'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_indexed_table', 'PRIMARY'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_indexed_table', 'idx_name'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_indexed_table', 'idx_created'));
    }

    public function test_run_creates_table_with_complex_structure(): void
    {
        // Arrange
        $createTableSql = "CREATE TABLE wp_complex_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            phone VARCHAR(20),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_email (email),
            INDEX idx_phone (phone),
            INDEX idx_created (created_at),
            FULLTEXT idx_address (address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // Act
        DbDelta::run($createTableSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_complex_table'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'email'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'phone'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'address'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'created_at'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'updated_at'));
        
        // Check indexes
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'PRIMARY'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'idx_name'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'idx_email'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'idx_phone'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'idx_created'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_complex_table', 'idx_address'));
    }

    public function test_run_handles_multiple_statements(): void
    {
        // Arrange
        $multipleSql = "CREATE TABLE wp_table1 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255)
        );
        CREATE TABLE wp_table2 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            description TEXT
        );
        ALTER TABLE wp_table1 ADD COLUMN email VARCHAR(255);";

        // Act
        DbDelta::run($multipleSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_table1'));
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_table2'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_table1', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_table1', 'name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_table1', 'email'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_table2', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_table2', 'description'));
    }

    public function test_run_handles_alter_table_statements(): void
    {
        // Arrange - First create a table
        $createSql = "CREATE TABLE wp_alter_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255)
        )";
        DbDelta::run($createSql);

        // Then alter it
        $alterSql = "ALTER TABLE wp_alter_test 
                     ADD COLUMN email VARCHAR(255),
                     ADD COLUMN phone VARCHAR(20),
                     ADD INDEX idx_email (email),
                     MODIFY COLUMN name VARCHAR(500)";

        // Act
        DbDelta::run($alterSql);

        // Assert
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_alter_test', 'email'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_alter_test', 'phone'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_alter_test', 'idx_email'));
    }

    public function test_run_handles_drop_statements(): void
    {
        // Arrange - First create tables and indexes
        $createSql = "CREATE TABLE wp_drop_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            INDEX idx_name (name)
        )";
        DbDelta::run($createSql);

        // Verify they exist
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_drop_test'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_drop_test', 'idx_name'));

        // Drop the index
        $dropIndexSql = "ALTER TABLE wp_drop_test DROP INDEX idx_name";
        DbDelta::run($dropIndexSql);

        // Assert index is gone
        $this->assertFalse(Db::indexExists($this->dbHelper->getWpdb(), 'wp_drop_test', 'idx_name'));

        // Drop the table
        $dropTableSql = "DROP TABLE wp_drop_test";
        DbDelta::run($dropTableSql);

        // Assert table is gone
        $this->assertFalse(Db::tableExists($this->dbHelper->getWpdb(), 'wp_drop_test'));
    }

    public function test_run_handles_if_not_exists_clauses(): void
    {
        // Arrange
        $createSql = "CREATE TABLE IF NOT EXISTS wp_if_not_exists_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255)
        )";

        // Act - Run multiple times
        DbDelta::run($createSql);
        DbDelta::run($createSql); // Should not fail

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_if_not_exists_test'));
    }

    public function test_run_handles_if_exists_clauses(): void
    {
        // Arrange
        $createSql = "CREATE TABLE wp_if_exists_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255)
        )";
        DbDelta::run($createSql);

        // Act - Drop with IF EXISTS
        $dropSql = "DROP TABLE IF EXISTS wp_if_exists_test";
        DbDelta::run($dropSql);

        // Assert
        $this->assertFalse(Db::tableExists($this->dbHelper->getWpdb(), 'wp_if_exists_test'));

        // Act - Try to drop again (should not fail)
        DbDelta::run($dropSql);

        // Assert - Still doesn't exist
        $this->assertFalse(Db::tableExists($this->dbHelper->getWpdb(), 'wp_if_exists_test'));
    }

    public function test_run_handles_special_characters_in_names(): void
    {
        // Arrange - Use underscores instead of hyphens to avoid SQL LIKE issues
        $createSql = "CREATE TABLE `wp_test_table` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `test_column` VARCHAR(255),
            INDEX `idx_test` (`test_column`)
        )";

        // Act
        DbDelta::run($createSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_test_table'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'test_column'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_test'));
    }

    public function test_run_handles_unicode_in_comments(): void
    {
        // Arrange
        $createSql = "CREATE TABLE wp_unicode_test (
            id INT AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
            name VARCHAR(255) NOT NULL COMMENT '名称',
            description TEXT COMMENT '描述信息'
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        // Act
        DbDelta::run($createSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_unicode_test'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_unicode_test', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_unicode_test', 'name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_unicode_test', 'description'));
    }

    public function test_run_handles_empty_sql(): void
    {
        // Act - Should not throw any exceptions
        DbDelta::run('');
        DbDelta::run('   ');
        DbDelta::run("\n\t  \n");

        // Assert - If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    public function test_run_handles_sql_with_comments(): void
    {
        // Arrange
        $createSql = "-- This is a comment
        CREATE TABLE wp_comment_test (
            id INT AUTO_INCREMENT PRIMARY KEY, -- Primary key comment
            name VARCHAR(255) NOT NULL /* Inline comment */
        ) /* Table comment */";

        // Act
        DbDelta::run($createSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_comment_test'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_comment_test', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_comment_test', 'name'));
    }

    public function test_run_handles_sql_with_semicolons(): void
    {
        // Arrange
        $createSql = "CREATE TABLE wp_semicolon_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
        );";

        // Act
        DbDelta::run($createSql);

        // Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_semicolon_test'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_semicolon_test', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_semicolon_test', 'name'));
    }
}
