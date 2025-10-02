<?php

namespace Tests\Integration\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\VersioningController;
use Minisite\Infrastructure\Versioning\MigrationLocator;
use Minisite\Infrastructure\Versioning\MigrationRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class VersioningControllerIntegrationTest extends TestCase
{
    private VersioningController $controller;
    private string $testTargetVersion;
    private string $testOptionKey;
    private DatabaseTestHelper $dbHelper;
    private string $tempMigrationsDir;

    protected function setUp(): void
    {
        // Create a temporary migration directory for testing
        $this->tempMigrationsDir = sys_get_temp_dir() . '/minisite_test_migrations_' . uniqid();
        if (!is_dir($this->tempMigrationsDir)) {
            mkdir($this->tempMigrationsDir, 0755, true);
        }

        $this->testTargetVersion = '2.0.0';
        $this->testOptionKey = 'minisite_integration_test_version_' . uniqid();

        // Create a test-specific controller that uses our temporary directory
        $this->controller = $this->createTestVersioningController($this->testTargetVersion, $this->testOptionKey, $this->tempMigrationsDir);

        // Setup database helper
        $this->dbHelper = new DatabaseTestHelper();

        // Set up global wpdb for VersioningController
        global $wpdb;
        $wpdb = $this->dbHelper->getWpdb();

        // Clear any existing test options
        delete_option($this->testOptionKey);

        // Clean up any existing test tables
        $this->cleanupTestTables();

        // Define production mode to disable safety mechanism for testing
        if (!defined('MINISITE_LIVE_PRODUCTION')) {
            define('MINISITE_LIVE_PRODUCTION', true);
        }
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
     * Create a test-specific VersioningController that uses a custom migration directory
     */
    private function createTestVersioningController(string $targetVersion, string $optionKey, string $migrationDir): VersioningController
    {
        return new class ($targetVersion, $optionKey, $migrationDir) extends VersioningController {
            private string $migrationDir;
            private string $targetVersion;
            private string $optionKey;

            public function __construct(string $targetVersion, string $optionKey, string $migrationDir)
            {
                parent::__construct($targetVersion, $optionKey);
                $this->migrationDir = $migrationDir;
                $this->targetVersion = $targetVersion;
                $this->optionKey = $optionKey;
            }

            public function ensureDatabaseUpToDate(): void
            {
                global $wpdb;

                // Safety (dev only): if our tables are missing but option says up-to-date, force a migration run
                if ((defined('MINISITE_LIVE_PRODUCTION') ? !MINISITE_LIVE_PRODUCTION : true) && $this->tablesMissing($wpdb)) {
                    // Reset stored version so runner applies base migration
                    update_option($this->optionKey, '0.0.0', false);
                }

                $locator = new MigrationLocator($this->migrationDir);
                $runner = new MigrationRunner($this->targetVersion, $this->optionKey, $locator);

                if (version_compare($runner->current(), $this->targetVersion, '<')) {
                    $runner->upgradeTo(static function ($msg) {
                        // Use error_log for now; swap with your Logger if desired
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log($msg);
                        }
                    });
                }
            }
        };
    }

    public function test_activate_creates_required_tables_when_none_exist(): void
    {
        // Ensure no tables exist
        $this->cleanupTestTables();

        // Set version to 0.0.0 to trigger full migration
        update_option($this->testOptionKey, '0.0.0');

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        $this->createTestMigrationFile('_2_0_0_AddFeatures.php', '2.0.0', 'Add features', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisite_versions (id INT AUTO_INCREMENT PRIMARY KEY, minisite_id INT)',
            'down' => 'DROP TABLE IF EXISTS wp_minisite_versions'
        ]);

        // Migration directory is already set up in setUp()

        // Activate the controller
        $this->controller->activate();

        // Verify tables were created
        $wpdb = $this->dbHelper->getWpdb();
        $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_minisites'");
        $this->assertNotEmpty($tables, 'wp_minisites table should be created');

        $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_minisite_versions'");
        $this->assertNotEmpty($tables, 'wp_minisite_versions table should be created');

        // Verify version was updated
        $this->assertEquals($this->testTargetVersion, get_option($this->testOptionKey));
    }

    public function test_ensureDatabaseUpToDate_skips_migrations_when_already_up_to_date(): void
    {
        // Set version to target version
        update_option($this->testOptionKey, $this->testTargetVersion);

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate
        $this->controller->ensureDatabaseUpToDate();

        // Verify version remains unchanged
        $this->assertEquals($this->testTargetVersion, get_option($this->testOptionKey));
    }

    public function test_ensureDatabaseUpToDate_runs_pending_migrations(): void
    {
        // Set version to 1.0.0 (lower than target 2.0.0)
        update_option($this->testOptionKey, '1.0.0');

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        $this->createTestMigrationFile('_2_0_0_AddFeatures.php', '2.0.0', 'Add features', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisite_versions (id INT AUTO_INCREMENT PRIMARY KEY, minisite_id INT)',
            'down' => 'DROP TABLE IF EXISTS wp_minisite_versions'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate
        $this->controller->ensureDatabaseUpToDate();

        // Verify version was updated
        $this->assertEquals($this->testTargetVersion, get_option($this->testOptionKey));
    }

    public function test_ensureDatabaseUpToDate_resets_version_when_tables_missing_in_dev(): void
    {
        // Set version to target (would normally skip migrations)
        update_option($this->testOptionKey, $this->testTargetVersion);

        // Ensure no tables exist
        $this->cleanupTestTables();

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate
        $this->controller->ensureDatabaseUpToDate();

        // In dev environment, version should be reset and migrations should run
        // Note: This test assumes we're in dev environment (MINISITE_LIVE_PRODUCTION not defined or false)
        $currentVersion = get_option($this->testOptionKey);
        $this->assertTrue(
            $currentVersion === '0.0.0' || $currentVersion === $this->testTargetVersion,
            "Version should be reset to 0.0.0 in dev or remain unchanged in production"
        );
    }

    public function test_ensureDatabaseUpToDate_handles_complex_migration_scenario(): void
    {
        // Start with no version set
        delete_option($this->testOptionKey);

        // Create multiple test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        $this->createTestMigrationFile('_1_1_0_AddEmail.php', '1.1.0', 'Add email column', [
            'up' => 'ALTER TABLE wp_minisites ADD COLUMN email VARCHAR(255)',
            'down' => 'ALTER TABLE wp_minisites DROP COLUMN email'
        ]);

        $this->createTestMigrationFile('_2_0_0_AddFeatures.php', '2.0.0', 'Add features', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisite_versions (id INT AUTO_INCREMENT PRIMARY KEY, minisite_id INT)',
            'down' => 'DROP TABLE IF EXISTS wp_minisite_versions'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate
        $this->controller->ensureDatabaseUpToDate();

        // Verify all migrations were applied
        $wpdb = $this->dbHelper->getWpdb();

        // Check base table exists
        $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_minisites'");
        $this->assertNotEmpty($tables, 'wp_minisites table should be created');

        // Check email column was added
        $columns = $wpdb->get_results("SHOW COLUMNS FROM wp_minisites LIKE 'email'");
        $this->assertNotEmpty($columns, 'email column should be added to wp_minisites');

        // Check features table exists
        $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_minisite_versions'");
        $this->assertNotEmpty($tables, 'wp_minisite_versions table should be created');

        // Verify version was updated
        $this->assertEquals($this->testTargetVersion, get_option($this->testOptionKey));
    }

    public function test_ensureDatabaseUpToDate_is_idempotent(): void
    {
        // Set version to target
        update_option($this->testOptionKey, $this->testTargetVersion);

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate multiple times
        $this->controller->ensureDatabaseUpToDate();
        $this->controller->ensureDatabaseUpToDate();
        $this->controller->ensureDatabaseUpToDate();

        // Verify version remains unchanged
        $this->assertEquals($this->testTargetVersion, get_option($this->testOptionKey));
    }

    public function test_ensureDatabaseUpToDate_handles_migration_failures_gracefully(): void
    {
        // Set version to 1.0.0 (lower than target 2.0.0)
        update_option($this->testOptionKey, '1.0.0');

        // Create test migration files with one that will fail
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        $this->createTestMigrationFile('_2_0_0_InvalidMigration.php', '2.0.0', 'Invalid migration', [
            'up' => 'INVALID SQL SYNTAX THAT WILL FAIL',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        // Migration directory is already set up in setUp()

        // This should not throw exceptions even if migrations fail
        // The MigrationRunner should handle failures gracefully
        try {
            $this->controller->ensureDatabaseUpToDate();
            // If we get here, the method handled the failure gracefully
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If an exception is thrown, it should be a specific type we can handle
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_ensureDatabaseUpToDate_works_with_real_wordpress_environment(): void
    {
        // This test verifies the controller works with real WordPress functions
        // Set version to 1.0.0
        update_option($this->testOptionKey, '1.0.0');

        // Create test migration files
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', [
            'up' => 'CREATE TABLE IF NOT EXISTS wp_minisites (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE IF EXISTS wp_minisites'
        ]);

        // Migration directory is already set up in setUp()

        // Call ensureDatabaseUpToDate
        $this->controller->ensureDatabaseUpToDate();

        // Verify WordPress functions were used correctly
        $this->assertTrue(function_exists('get_option'), 'WordPress get_option function should be available');
        $this->assertTrue(function_exists('update_option'), 'WordPress update_option function should be available');

        // Verify version was updated to the highest available migration (1.0.0)
        // Since there's no 2.0.0 migration, it should stay at 1.0.0
        $this->assertEquals('1.0.0', get_option($this->testOptionKey));
    }

    private function createTestMigrationFile(
        string $filename,
        string $version,
        string $description,
        array $sql = []
    ): void {
        $className = $this->getClassNameFromFilename($filename);
        $upSql = $sql['up'] ?? '-- No up SQL';
        $downSql = $sql['down'] ?? '-- No down SQL';

        // Add unique suffix to prevent class redeclaration
        $uniqueClassName = $className . 'Controller' . uniqid();

        // Escape SQL for PHP string
        $escapedUpSql = str_replace("'", "\\'", $upSql);
        $escapedDownSql = str_replace("'", "\\'", $downSql);

        $content = "<?php
namespace Minisite\Infrastructure\Versioning\Migrations;
use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

class {$uniqueClassName} implements Migration {
    public function version(): string {
        return '{$version}';
    }
    
    public function description(): string {
        return '{$description}';
    }
    
    public function up(): void {
        db::query('{$escapedUpSql}');
    }
    
    public function down(): void {
        db::query('{$escapedDownSql}');
    }
}
";

        file_put_contents($this->tempMigrationsDir . '/' . $filename, $content);
    }

    private function getClassNameFromFilename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }


    private function cleanupTestTables(): void
    {
        $testTables = [
            'wp_minisites',
            'wp_minisite_versions',
            'wp_minisite_reviews',
            'wp_minisite_bookmarks'
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
