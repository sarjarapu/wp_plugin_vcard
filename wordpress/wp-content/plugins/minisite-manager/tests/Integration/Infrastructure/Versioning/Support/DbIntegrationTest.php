<?php

namespace Tests\Integration\Infrastructure\Versioning\Support;

use Minisite\Infrastructure\Versioning\Support\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class DbIntegrationTest extends TestCase
{
    private DatabaseTestHelper $dbHelper;
    private $originalWpdb;

    protected function setUp(): void
    {
        // Store the original $wpdb to restore later
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

        // Set up database helper
        $this->dbHelper = new DatabaseTestHelper();

        // Clean up any existing test data
        $this->dbHelper->cleanupTestTables();

        // Create a test table for our tests
        $this->createTestTable();

        // Set the global $wpdb to our test database
        $GLOBALS['wpdb'] = $this->dbHelper->getWpdb();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dbHelper->cleanupTestTables();

        // Restore the original $wpdb
        $GLOBALS['wpdb'] = $this->originalWpdb;
    }

    private function createTestTable(): void
    {
        $this->dbHelper->exec("
            CREATE TABLE wp_test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function test_tableExists_returns_true_for_existing_table(): void
    {
        // Act
        $result = Db::tableExists($this->dbHelper->getWpdb(), 'wp_test_table');

        // Assert
        $this->assertTrue($result);
    }

    public function test_tableExists_returns_false_for_nonexistent_table(): void
    {
        // Act
        $result = Db::tableExists($this->dbHelper->getWpdb(), 'wp_nonexistent_table');

        // Assert
        $this->assertFalse($result);
    }

    public function test_tableExists_handles_different_table_names(): void
    {
        // Create additional test tables
        $this->dbHelper->exec("CREATE TABLE IF NOT EXISTS wp_another_table (id INT)");
        $this->dbHelper->exec("CREATE TABLE IF NOT EXISTS wp_third_table (id INT)");

        // Act & Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_test_table'));
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_another_table'));
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), 'wp_third_table'));
        $this->assertFalse(Db::tableExists($this->dbHelper->getWpdb(), 'wp_fourth_table'));
    }

    public function test_columnExists_returns_true_for_existing_columns(): void
    {
        // Act & Assert
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'email'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'created_at'));
    }

    public function test_columnExists_returns_false_for_nonexistent_columns(): void
    {
        // Act & Assert
        $this->assertFalse(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'nonexistent_column'));
        $this->assertFalse(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'fake_field'));
        $this->assertFalse(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'deleted_at'));
    }

    public function test_columnExists_handles_case_sensitivity(): void
    {
        // Act & Assert - MySQL column names are case-insensitive by default
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'ID'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'Name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'EMAIL'));
    }

    public function test_indexExists_returns_true_for_existing_indexes(): void
    {
        // Act & Assert
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'PRIMARY'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_name'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_email'));
    }

    public function test_indexExists_returns_false_for_nonexistent_indexes(): void
    {
        // Act & Assert
        $this->assertFalse(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'nonexistent_index'));
        $this->assertFalse(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'fake_idx'));
    }

    public function test_indexExists_handles_case_sensitivity(): void
    {
        // Act & Assert - MySQL index names are case-insensitive by default
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'primary'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'IDX_NAME'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'Idx_Email'));
    }

    public function test_all_methods_work_with_dynamic_table_creation(): void
    {
        // Create a new table dynamically
        $tableName = 'wp_dynamic_table';
        $this->dbHelper->exec("
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dynamic_column VARCHAR(255),
                INDEX idx_dynamic (dynamic_column)
            )
        ");

        // Act & Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), $tableName));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $tableName, 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $tableName, 'dynamic_column'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $tableName, 'PRIMARY'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $tableName, 'idx_dynamic'));
    }

    public function test_methods_handle_table_modifications(): void
    {
        // Add a new column and index
        $this->dbHelper->exec("ALTER TABLE wp_test_table ADD COLUMN new_column VARCHAR(255)");
        $this->dbHelper->exec("ALTER TABLE wp_test_table ADD INDEX idx_new_column (new_column)");

        // Act & Assert
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'new_column'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_new_column'));
    }

    public function test_methods_handle_table_dropping(): void
    {
        // Create a temporary table
        $tempTable = 'wp_temp_table';
        $this->dbHelper->exec("CREATE TABLE {$tempTable} (id INT)");

        // Verify it exists
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), $tempTable));

        // Drop the table
        $this->dbHelper->exec("DROP TABLE {$tempTable}");

        // Verify it no longer exists
        $this->assertFalse(Db::tableExists($this->dbHelper->getWpdb(), $tempTable));
    }

    public function test_methods_handle_index_dropping(): void
    {
        // Create a temporary index
        $this->dbHelper->exec("ALTER TABLE wp_test_table ADD INDEX idx_temp (name)");

        // Verify it exists
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_temp'));

        // Drop the index
        $this->dbHelper->exec("ALTER TABLE wp_test_table DROP INDEX idx_temp");

        // Verify it no longer exists
        $this->assertFalse(Db::indexExists($this->dbHelper->getWpdb(), 'wp_test_table', 'idx_temp'));
    }

    public function test_methods_handle_column_dropping(): void
    {
        // Add a temporary column
        $this->dbHelper->exec("ALTER TABLE wp_test_table ADD COLUMN temp_column VARCHAR(255)");

        // Verify it exists
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'temp_column'));

        // Drop the column
        $this->dbHelper->exec("ALTER TABLE wp_test_table DROP COLUMN temp_column");

        // Verify it no longer exists
        $this->assertFalse(Db::columnExists($this->dbHelper->getWpdb(), 'wp_test_table', 'temp_column'));
    }

    public function test_methods_work_with_complex_table_structure(): void
    {
        // Create a complex table
        $complexTable = 'wp_complex_table';
        $this->dbHelper->exec("
            CREATE TABLE IF NOT EXISTS {$complexTable} (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Act & Assert - Test all columns
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'id'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'name'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'email'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'phone'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'address'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'created_at'));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $complexTable, 'updated_at'));

        // Act & Assert - Test all indexes
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'PRIMARY'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'idx_name'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'idx_email'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'idx_phone'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'idx_created'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $complexTable, 'idx_address'));
    }

    public function test_methods_handle_special_characters_in_names(): void
    {
        // Create table with special characters in names
        $specialTable = 'wp_test_table_with_underscores';
        $this->dbHelper->exec("
            CREATE TABLE IF NOT EXISTS {$specialTable} (
                test_column_with_underscores VARCHAR(255),
                INDEX idx_test_with_underscores (test_column_with_underscores)
            )
        ");

        // Act & Assert
        $this->assertTrue(Db::tableExists($this->dbHelper->getWpdb(), $specialTable));
        $this->assertTrue(Db::columnExists($this->dbHelper->getWpdb(), $specialTable, 'test_column_with_underscores'));
        $this->assertTrue(Db::indexExists($this->dbHelper->getWpdb(), $specialTable, 'idx_test_with_underscores'));
    }
}
