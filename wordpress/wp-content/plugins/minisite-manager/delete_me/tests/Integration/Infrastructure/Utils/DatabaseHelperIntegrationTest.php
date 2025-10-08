<?php

namespace Tests\Integration\Infrastructure\Utils;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabaseUtils;
use Minisite\Infrastructure\Utils\DatabaseHelper;

/**
 * Integration tests for DatabaseHelper class
 * 
 * These tests use a real MySQL database connection to verify that DatabaseHelper
 * works correctly with actual database operations.
 */
class DatabaseHelperIntegrationTest extends TestCase
{
    private TestDatabaseUtils $dbHelper;
    private string $testTable;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dbHelper = new TestDatabaseUtils();
        $this->testTable = $this->dbHelper->getWpdb()->prefix . 'test_table';
        
        // Set global $wpdb for DatabaseHelper to use
        global $wpdb;
        $wpdb = $this->dbHelper->getWpdb();
        
        // Create test table
        $this->createTestTable();
    }

    protected function tearDown(): void
    {
        // Clean up test table
        $this->dropTestTable();
        parent::tearDown();
    }

    /**
     * Create test table for integration tests
     */
    private function createTestTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->testTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $this->dbHelper->getWpdb()->query($sql);
    }

    /**
     * Drop test table
     */
    private function dropTestTable(): void
    {
        $sql = "DROP TABLE IF EXISTS {$this->testTable}";
        $this->dbHelper->getWpdb()->query($sql);
    }

    /**
     * Test get_var with real database
     */
    public function testGetVarWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test counting records
        $count = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(3, (int) $count);
        
        // Test getting specific value
        $name = DatabaseHelper::get_var(
            "SELECT name FROM {$this->testTable} WHERE id = %d",
            [1]
        );
        $this->assertEquals('John Doe', $name);
        
        // Test getting non-existent value
        $nonExistent = DatabaseHelper::get_var(
            "SELECT name FROM {$this->testTable} WHERE id = %d",
            [999]
        );
        $this->assertNull($nonExistent);
    }

    /**
     * Test get_row with real database
     */
    public function testGetRowWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test getting single row
        $row = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [1]
        );
        
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['id']);
        $this->assertEquals('John Doe', $row['name']);
        $this->assertEquals('john@example.com', $row['email']);
        $this->assertEquals('active', $row['status']);
        
        // Test getting non-existent row
        $nonExistent = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [999]
        );
        $this->assertNull($nonExistent);
    }

    /**
     * Test get_results with real database
     */
    public function testGetResultsWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test getting all results
        $results = DatabaseHelper::get_results("SELECT * FROM {$this->testTable} ORDER BY id");
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        // Verify first result
        $this->assertEquals(1, $results[0]['id']);
        $this->assertEquals('John Doe', $results[0]['name']);
        
        // Verify second result
        $this->assertEquals(2, $results[1]['id']);
        $this->assertEquals('Jane Smith', $results[1]['name']);
        
        // Test getting filtered results
        $activeResults = DatabaseHelper::get_results(
            "SELECT * FROM {$this->testTable} WHERE status = %s ORDER BY id",
            ['active']
        );
        
        $this->assertCount(2, $activeResults);
        $this->assertEquals('active', $activeResults[0]['status']);
        $this->assertEquals('active', $activeResults[1]['status']);
    }

    /**
     * Test query with real database
     */
    public function testQueryWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test UPDATE query
        $affectedRows = DatabaseHelper::query(
            "UPDATE {$this->testTable} SET status = %s WHERE id = %d",
            ['inactive', 1]
        );
        
        $this->assertEquals(1, $affectedRows);
        
        // Verify the update
        $row = DatabaseHelper::get_row(
            "SELECT status FROM {$this->testTable} WHERE id = %d",
            [1]
        );
        $this->assertEquals('inactive', $row['status']);
        
        // Test DELETE query
        $affectedRows = DatabaseHelper::query(
            "DELETE FROM {$this->testTable} WHERE id = %d",
            [3]
        );
        
        $this->assertEquals(1, $affectedRows);
        
        // Verify the deletion
        $count = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(2, (int) $count);
    }

    /**
     * Test insert with real database
     */
    public function testInsertWithRealDatabase(): void
    {
        // Test successful insert
        $result = DatabaseHelper::insert(
            $this->testTable,
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active'
            ],
            ['%s', '%s', '%s']
        );
        
        $this->assertEquals(1, $result);
        
        // Verify the insert
        $insertId = DatabaseHelper::get_insert_id();
        $this->assertGreaterThan(0, $insertId);
        
        $row = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [$insertId]
        );
        
        $this->assertEquals('Test User', $row['name']);
        $this->assertEquals('test@example.com', $row['email']);
        $this->assertEquals('active', $row['status']);
    }

    /**
     * Test insert with default format
     */
    public function testInsertWithDefaultFormat(): void
    {
        $result = DatabaseHelper::insert(
            $this->testTable,
            [
                'name' => 'Test User 2',
                'email' => 'test2@example.com',
                'status' => 'active'
            ]
        );
        
        $this->assertEquals(1, $result);
        
        $insertId = DatabaseHelper::get_insert_id();
        $this->assertGreaterThan(0, $insertId);
    }

    /**
     * Test update with real database
     */
    public function testUpdateWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test successful update
        $result = DatabaseHelper::update(
            $this->testTable,
            ['name' => 'Updated Name', 'status' => 'inactive'],
            ['id' => 1],
            ['%s', '%s'],
            ['%d']
        );
        
        $this->assertEquals(1, $result);
        
        // Verify the update
        $row = DatabaseHelper::get_row(
            "SELECT name, status FROM {$this->testTable} WHERE id = %d",
            [1]
        );
        
        $this->assertEquals('Updated Name', $row['name']);
        $this->assertEquals('inactive', $row['status']);
    }

    /**
     * Test update with default formats
     */
    public function testUpdateWithDefaultFormats(): void
    {
        // Insert test data
        $this->insertTestData();
        
        $result = DatabaseHelper::update(
            $this->testTable,
            ['name' => 'Updated Name 2'],
            ['id' => 2]
        );
        
        $this->assertEquals(1, $result);
        
        $row = DatabaseHelper::get_row(
            "SELECT name FROM {$this->testTable} WHERE id = %d",
            [2]
        );
        
        $this->assertEquals('Updated Name 2', $row['name']);
    }

    /**
     * Test delete with real database
     */
    public function testDeleteWithRealDatabase(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test successful delete
        $result = DatabaseHelper::delete(
            $this->testTable,
            ['id' => 1],
            ['%d']
        );
        
        $this->assertEquals(1, $result);
        
        // Verify the deletion
        $count = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(2, (int) $count);
        
        $row = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [1]
        );
        $this->assertNull($row);
    }

    /**
     * Test delete with default format
     */
    public function testDeleteWithDefaultFormat(): void
    {
        // Insert test data
        $this->insertTestData();
        
        $result = DatabaseHelper::delete(
            $this->testTable,
            ['id' => 2]
        );
        
        $this->assertEquals(1, $result);
        
        $count = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(2, (int) $count);
    }

    /**
     * Test get_insert_id with real database
     */
    public function testGetInsertIdWithRealDatabase(): void
    {
        // Insert test data
        $result = DatabaseHelper::insert(
            $this->testTable,
            [
                'name' => 'Insert ID Test',
                'email' => 'insert@example.com',
                'status' => 'active'
            ]
        );
        
        $this->assertEquals(1, $result);
        
        $insertId = DatabaseHelper::get_insert_id();
        $this->assertGreaterThan(0, $insertId);
        $this->assertIsInt($insertId);
        
        // Verify the record exists
        $row = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [$insertId]
        );
        
        $this->assertEquals('Insert ID Test', $row['name']);
    }

    /**
     * Test transaction-like behavior
     */
    public function testTransactionLikeBehavior(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Perform multiple operations
        $result1 = DatabaseHelper::update(
            $this->testTable,
            ['status' => 'inactive'],
            ['id' => 1]
        );
        
        $result2 = DatabaseHelper::update(
            $this->testTable,
            ['status' => 'inactive'],
            ['id' => 2]
        );
        
        $this->assertEquals(1, $result1);
        $this->assertEquals(1, $result2);
        
        // Verify both updates (id 1 and 2 are now inactive, id 3 was already inactive)
        $inactiveCount = DatabaseHelper::get_var(
            "SELECT COUNT(*) FROM {$this->testTable} WHERE status = %s",
            ['inactive']
        );
        
        $this->assertEquals(3, (int) $inactiveCount); // All 3 records should be inactive now
        
        // Verify total count is still 3
        $totalCount = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(3, (int) $totalCount);
    }

    /**
     * Test error handling with invalid SQL
     */
    public function testErrorHandlingWithInvalidSql(): void
    {
        // This should throw an exception for invalid SQL
        $this->expectException(\PDOException::class);
        DatabaseHelper::query("INVALID SQL STATEMENT");
    }

    /**
     * Test error handling with invalid table
     */
    public function testErrorHandlingWithInvalidTable(): void
    {
        // This should throw an exception for non-existent table
        $this->expectException(\PDOException::class);
        DatabaseHelper::insert(
            'non_existent_table',
            ['name' => 'test'],
            ['%s']
        );
    }

    /**
     * Test parameter binding with special characters
     */
    public function testParameterBindingWithSpecialCharacters(): void
    {
        // Insert test data with special characters
        $result = DatabaseHelper::insert(
            $this->testTable,
            [
                'name' => "Test's Name with \"quotes\" and 'apostrophes'",
                'email' => 'test+tag@example.com',
                'status' => 'active'
            ]
        );
        
        $this->assertEquals(1, $result);
        
        $insertId = DatabaseHelper::get_insert_id();
        
        // Retrieve and verify the data
        $row = DatabaseHelper::get_row(
            "SELECT name, email FROM {$this->testTable} WHERE id = %d",
            [$insertId]
        );
        
        $this->assertEquals("Test's Name with \"quotes\" and 'apostrophes'", $row['name']);
        $this->assertEquals('test+tag@example.com', $row['email']);
    }

    /**
     * Test parameter binding with numeric values
     */
    public function testParameterBindingWithNumericValues(): void
    {
        // Insert test data
        $this->insertTestData();
        
        // Test with integer parameter
        $count = DatabaseHelper::get_var(
            "SELECT COUNT(*) FROM {$this->testTable} WHERE id > %d",
            [1]
        );
        $this->assertEquals(2, (int) $count);
        
        // Test with float parameter (if we had a float column)
        $row = DatabaseHelper::get_row(
            "SELECT * FROM {$this->testTable} WHERE id = %d",
            [2]
        );
        $this->assertEquals(2, $row['id']);
    }

    /**
     * Test empty result sets
     */
    public function testEmptyResultSets(): void
    {
        // Test empty table
        $count = DatabaseHelper::get_var("SELECT COUNT(*) FROM {$this->testTable}");
        $this->assertEquals(0, (int) $count);
        
        $results = DatabaseHelper::get_results("SELECT * FROM {$this->testTable}");
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
        
        $row = DatabaseHelper::get_row("SELECT * FROM {$this->testTable} LIMIT 1");
        $this->assertNull($row);
    }

    /**
     * Insert test data for integration tests
     */
    private function insertTestData(): void
    {
        $testData = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'active'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'status' => 'inactive']
        ];
        
        foreach ($testData as $data) {
            DatabaseHelper::insert(
                $this->testTable,
                $data,
                ['%s', '%s', '%s']
            );
        }
    }
}
