<?php

namespace Tests\Integration\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\MigrationRunner;
use Minisite\Infrastructure\Versioning\MigrationLocator;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\DatabaseTestHelper;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class MigrationRunnerIntegrationTest extends TestCase
{
    private MigrationRunner $runner;
    private MigrationLocator $locator;
    private string $tempMigrationsDir;
    private DatabaseTestHelper $dbHelper;
    private string $testOptionKey = 'minisite_integration_test_version';

    protected function setUp(): void
    {
        // Create temporary directory for test migrations
        $this->tempMigrationsDir = realpath(sys_get_temp_dir()) . '/minisite_migrations_integration_test_' . uniqid();
        mkdir($this->tempMigrationsDir, 0755, true);

        // Setup database helper
        $this->dbHelper = new DatabaseTestHelper();

        // Create locator and runner
        $this->locator = new MigrationLocator($this->tempMigrationsDir);
        $this->runner = new MigrationRunner('2.0.0', $this->testOptionKey, $this->locator);

        // Clear any existing test options
        delete_option($this->testOptionKey);

        // Clean up any existing test tables
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        $this->removeDirectory($this->tempMigrationsDir);

        // Clean up test options
        delete_option($this->testOptionKey);

        // Clean up test tables
        $this->cleanupTestTables();
    }

    /**
     * Clean up test tables created during tests
     */
    private function cleanupTestTables(): void
    {
        $testTables = [
            'test_users',
            'test_posts',
            'test_table'
        ];

        $wpdb = $this->dbHelper->getWpdb();
        foreach ($testTables as $table) {
            try {
                $wpdb->query("DROP TABLE IF EXISTS {$table}");
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
    }

    public function test_upgradeTo_runs_real_migrations_and_creates_tables(): void
    {
        // Arrange - Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmailRunner.php', '1.1.0', 'Add email column to users', [
            'up' => 'ALTER TABLE test_users ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE test_users DROP COLUMN email'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $this->runner->upgradeTo($logger);

        // Assert
        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('1.0.0', $loggedMessages[0]);
        $this->assertStringContainsString('Create users table', $loggedMessages[0]);
        $this->assertStringContainsString('1.1.0', $loggedMessages[1]);
        $this->assertStringContainsString('Add email column to users', $loggedMessages[1]);

        // Verify database state
        $wpdb = $this->dbHelper->getWpdb();
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_users'");
        $this->assertNotNull($tableExists, 'Users table should exist');

        $columns = $wpdb->get_results("SHOW COLUMNS FROM test_users");
        $columnNames = array_column($columns, 'Field');
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);

        // Verify version was updated
        $this->assertEquals('1.1.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_skips_already_applied_migrations(): void
    {
        // Arrange - Set current version to 1.0.0
        update_option($this->testOptionKey, '1.0.0');

        // Create the table that would have been created by the 1.0.0 migration
        $wpdb = $this->dbHelper->getWpdb();
        $wpdb->query('CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        // Create migration files
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmailRunner.php', '1.1.0', 'Add email column to users', [
            'up' => 'ALTER TABLE test_users ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE test_users DROP COLUMN email'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $this->runner->upgradeTo($logger);

        // Assert - Only the second migration should run
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.1.0', $loggedMessages[0]);
        $this->assertStringNotContainsString('1.0.0', $loggedMessages[0]);

        // Verify version was updated
        $this->assertEquals('1.1.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_respects_target_version_limit(): void
    {
        // Arrange - Create runner with specific target version
        $runner = new MigrationRunner('1.0.0', $this->testOptionKey, $this->locator);

        // Create migration files
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmailRunner.php', '1.1.0', 'Add email column to users', [
            'up' => 'ALTER TABLE test_users ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE test_users DROP COLUMN email'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $runner->upgradeTo($this->dbHelper->getWpdb(), $logger);

        // Assert - Only the first migration should run (up to target version)
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.0.0', $loggedMessages[0]);
        $this->assertStringNotContainsString('1.1.0', $loggedMessages[0]);

        // Verify version was updated
        $this->assertEquals('1.0.0', get_option($this->testOptionKey));
    }

    public function test_downgradeTo_rolls_back_migrations(): void
    {
        // Arrange - Set up initial state with tables
        update_option($this->testOptionKey, '1.1.0');

        $wpdb = $this->dbHelper->getWpdb();
        $wpdb->query('CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255))');

        // Create migration files
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmailRunner.php', '1.1.0', 'Add email column to users', [
            'up' => 'ALTER TABLE test_users ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE test_users DROP COLUMN email'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $this->runner->downgradeTo($this->dbHelper->getWpdb(), '1.0.0', $logger);

        // Assert
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.1.0', $loggedMessages[0]);
        $this->assertStringContainsString('Reverting', $loggedMessages[0]);

        // Verify database state - email column should be removed
        $columns = $wpdb->get_results("SHOW COLUMNS FROM test_users");
        $columnNames = array_column($columns, 'Field');
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertNotContains('email', $columnNames);

        // Verify version was updated
        $this->assertEquals('1.0.0', get_option($this->testOptionKey));
    }

    public function test_downgradeTo_removes_tables_completely(): void
    {
        // Arrange - Set up initial state with table
        update_option($this->testOptionKey, '1.0.0');

        $wpdb = $this->dbHelper->getWpdb();
        $wpdb->query('CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        // Create migration file
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $this->runner->downgradeTo('0.0.0', $logger);

        // Assert
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.0.0', $loggedMessages[0]);
        $this->assertStringContainsString('Reverting', $loggedMessages[0]);

        // Verify table was removed
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_users'");
        $this->assertNull($tableExists, 'Users table should be removed');

        // Verify version was updated
        $this->assertEquals('0.0.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_handles_complex_migration_scenario(): void
    {
        // Arrange - Create multiple migration files
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmailRunner.php', '1.1.0', 'Add email column', [
            'up' => 'ALTER TABLE test_users ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE test_users DROP COLUMN email'
        ]);

        $this->createTestMigrationFile('_1_2_0_CreatePostsRunner.php', '1.2.0', 'Create posts table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_posts (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, user_id INT)',
            'down' => 'DROP TABLE IF EXISTS test_posts'
        ]);

        $this->createTestMigrationFile('_2_0_0_AddIndexesRunner.php', '2.0.0', 'Add database indexes', [
            'up' => 'ALTER TABLE test_users ADD INDEX idx_email (email); ALTER TABLE test_posts ADD INDEX idx_user_id (user_id)',
            'down' => 'ALTER TABLE test_users DROP INDEX idx_email; ALTER TABLE test_posts DROP INDEX idx_user_id'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act
        $this->runner->upgradeTo($logger);

        // Assert
        $this->assertCount(4, $loggedMessages);
        $this->assertStringContainsString('1.0.0', $loggedMessages[0]);
        $this->assertStringContainsString('1.1.0', $loggedMessages[1]);
        $this->assertStringContainsString('1.2.0', $loggedMessages[2]);
        $this->assertStringContainsString('2.0.0', $loggedMessages[3]);

        // Verify database state
        $wpdb = $this->dbHelper->getWpdb();

        // Check users table
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_users'");
        $this->assertNotNull($tableExists, 'Users table should exist');

        $columns = $wpdb->get_results("SHOW COLUMNS FROM test_users");
        $columnNames = array_column($columns, 'Field');
        $this->assertContains('email', $columnNames);

        // Check posts table
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_posts'");
        $this->assertNotNull($tableExists, 'Posts table should exist');

        // Check indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM test_users WHERE Key_name = 'idx_email'");
        $this->assertNotEmpty($indexes, 'Email index should exist');

        // Verify final version
        $this->assertEquals('2.0.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_is_idempotent(): void
    {
        // Arrange - Create migration file
        $this->createTestMigrationFile('_1_0_0_CreateUsersRunner.php', '1.0.0', 'Create users table', [
            'up' => 'CREATE TABLE IF NOT EXISTS test_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        $loggedMessages = [];
        $logger = function ($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };

        // Act - Run migration twice
        $this->runner->upgradeTo($logger);
        $loggedMessages = []; // Clear messages
        $this->runner->upgradeTo($logger);

        // Assert - Second run should not log anything (idempotent)
        $this->assertCount(0, $loggedMessages, 'Second run should not execute any migrations');

        // Verify table still exists
        $wpdb = $this->dbHelper->getWpdb();
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_users'");
        $this->assertNotNull($tableExists, 'Users table should still exist');
    }

    public function test_upgradeTo_handles_migration_failures_gracefully(): void
    {
        // Arrange - Create migration with invalid SQL
        $this->createTestMigrationFile('_1_0_0_InvalidMigrationRunner.php', '1.0.0', 'Invalid migration', [
            'up' => 'INVALID SQL SYNTAX',
            'down' => 'DROP TABLE IF EXISTS test_users'
        ]);

        // Act & Assert - Should throw exception
        $this->expectException(\Exception::class);
        $this->runner->upgradeTo($this->dbHelper->getWpdb());
    }

    /**
     * Create a test migration file with the given parameters
     */
    private function createTestMigrationFile(
        string $filename,
        string $version,
        string $description,
        array $sql = []
    ): void {
        $className = $this->getClassNameFromFilename($filename);
        $upSql = $sql['up'] ?? '-- Migration up SQL';
        $downSql = $sql['down'] ?? '-- Migration down SQL';

        $content = "<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            use Minisite\Infrastructure\Versioning\Contracts\Migration;
            
            class {$className} implements Migration {
                public function version(): string { 
                    return '{$version}'; 
                }
                public function description(): string { 
                    return '{$description}'; 
                }
                public function up(\wpdb \$wpdb): void {
                    \$wpdb->query('{$upSql}');
                }
                public function down(\wpdb \$wpdb): void {
                    \$wpdb->query('{$downSql}');
                }
            }
        ";

        $this->createFile($filename, $content);
    }

    /**
     * Create a file with the given filename and content
     */
    private function createFile(string $filename, string $content): void
    {
        $filepath = $this->tempMigrationsDir . '/' . $filename;
        file_put_contents($filepath, $content);
    }

    /**
     * Extract class name from filename (remove extension and underscores)
     */
    private function getClassNameFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return $name;
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
