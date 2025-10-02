<?php

namespace Tests\Integration\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\MigrationLocator;
use Minisite\Infrastructure\Versioning\Contracts\Migration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabaseUtils;

#[RunTestsInSeparateProcesses]
#[Group('integration')]
class MigrationLocatorIntegrationTest extends TestCase
{
    private string $tempMigrationsDir;
    private TestDatabaseUtils $dbHelper;

    protected function setUp(): void
    {
        // Create temporary directory for test migrations
        $this->tempMigrationsDir = realpath(sys_get_temp_dir()) . '/minisite_migrations_test_' . uniqid();
        mkdir($this->tempMigrationsDir, 0755, true);

        $this->dbHelper = new TestDatabaseUtils();

        // Clear any previously declared classes
        $this->clearDeclaredClasses();
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        $this->removeDirectory($this->tempMigrationsDir);
        $this->clearDeclaredClasses();
    }

    /**
     * Clear classes that were declared during testing
     */
    private function clearDeclaredClasses(): void
    {
        // This is a limitation of PHP - we can't truly clear declared classes
        // But we can reset the test environment for each test
    }

    public function test_all_loads_real_migration_files(): void
    {
        // Arrange
        $this->createTestMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables');
        $this->createTestMigrationFile('_1_1_0_AddIndexes.php', '1.1.0', 'Add database indexes');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Migration::class, $result[0]);
        $this->assertInstanceOf(Migration::class, $result[1]);

        // Verify versions are sorted correctly
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('1.1.0', $result[1]->version());

        // Verify descriptions
        $this->assertEquals('Create base tables', $result[0]->description());
        $this->assertEquals('Add database indexes', $result[1]->description());
    }

    public function test_all_handles_migrations_with_database_operations(): void
    {
        // Arrange
        $this->createTestMigrationFile('_1_0_0_CreateTables.php', '1.0.0', 'Create initial tables', [
            'up' => 'CREATE TABLE test_table (id INT PRIMARY KEY, name VARCHAR(255))',
            'down' => 'DROP TABLE test_table'
        ], 'DbCreateTables');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(1, $result);
        $migration = $result[0];
        $this->assertInstanceOf(Migration::class, $migration);

        // Test that the migration can be executed (up method)
        $wpdb = $this->dbHelper->getWpdb();
        // Set the global $wpdb for the migration to use
        global $wpdb;
        $wpdb = $this->dbHelper->getWpdb();
        $migration->up();

        // Verify table was created
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_table'");
        $this->assertNotNull($tableExists);

        // Test rollback (down method)
        $migration->down();

        // Verify table was dropped
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'test_table'");
        $this->assertNull($tableExists);
    }

    public function test_all_handles_complex_migration_scenarios(): void
    {
        // Arrange
        $this->createTestMigrationFile('_1_0_0_Initial_Complex.php', '1.0.0', 'Initial migration');
        $this->createTestMigrationFile('_1_0_1_Hotfix.php', '1.0.1', 'Hotfix migration');
        $this->createTestMigrationFile('_1_1_0_Features.php', '1.1.0', 'New features');
        $this->createTestMigrationFile('_2_0_0_Breaking.php', '2.0.0', 'Breaking changes');
        $this->createTestMigrationFile('_2_0_1_PostBreaking.php', '2.0.1', 'Post-breaking fix');

        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(5, $result);

        $expectedVersions = ['1.0.0', '1.0.1', '1.1.0', '2.0.0', '2.0.1'];
        $actualVersions = array_map(fn($migration) => $migration->version(), $result);
        $this->assertEquals($expectedVersions, $actualVersions);

        // Verify all migrations implement the interface correctly
        foreach ($result as $migration) {
            $this->assertInstanceOf(Migration::class, $migration);
            $this->assertIsString($migration->version());
            $this->assertIsString($migration->description());
            $this->assertTrue(method_exists($migration, 'up'));
            $this->assertTrue(method_exists($migration, 'down'));
        }
    }

    public function test_all_handles_migrations_with_different_file_naming_conventions(): void
    {
        // Arrange
        $this->createTestMigrationFile('Migration_1_0_0_CreateBase.php', '1.0.0', 'Create base tables');
        $this->createTestMigrationFile('V1_1_0_AddFeatures.php', '1.1.0', 'Add features');
        $this->createTestMigrationFile('_2_0_0_UpdateSchema.php', '2.0.0', 'Update schema');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(3, $result);

        $expectedVersions = ['1.0.0', '1.1.0', '2.0.0'];
        $actualVersions = array_map(fn($migration) => $migration->version(), $result);
        $this->assertEquals($expectedVersions, $actualVersions);
    }

    public function test_all_handles_migrations_with_complex_version_numbers(): void
    {
        // Arrange
        $this->createTestMigrationFile('_1_0_0_Initial.php', '1.0.0', 'Initial');
        $this->createTestMigrationFile('_1_0_0_alpha1_Alpha.php', '1.0.0-alpha1', 'Alpha');
        $this->createTestMigrationFile('_1_0_0_beta1_Beta.php', '1.0.0-beta1', 'Beta');
        $this->createTestMigrationFile('_1_0_0_rc1_RC.php', '1.0.0-rc1', 'Release Candidate');
        $this->createTestMigrationFile('_1_0_1_Patch.php', '1.0.1', 'Patch');
        $this->createTestMigrationFile('_1_10_0_Minor.php', '1.10.0', 'Minor');
        $this->createTestMigrationFile('_10_0_0_Major.php', '10.0.0', 'Major');

        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(7, $result);

        $expectedVersions = [
            '1.0.0-alpha1',
            '1.0.0-beta1',
            '1.0.0-rc1',
            '1.0.0',
            '1.0.1',
            '1.10.0',
            '10.0.0'
        ];
        $actualVersions = array_map(fn($migration) => $migration->version(), $result);
        $this->assertEquals($expectedVersions, $actualVersions);
    }

    public function test_all_ignores_invalid_migration_files(): void
    {
        // Arrange
        // Valid migration
        $this->createTestMigrationFile('_1_0_0_Valid.php', '1.0.0', 'Valid migration');

        // Invalid files
        $this->createFile('invalid_syntax.php', '<?php class Invalid { public function test() { return "test"; } } // Valid syntax but not a migration');
        $this->createFile('not_migration.php', '<?php class NotMigration {}');
        $this->createFile('wrong_interface.php', '<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            class WrongInterface {}
        ');

        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('Valid migration', $result[0]->description());
    }

    public function test_all_handles_migrations_with_namespace_conflicts(): void
    {
        // Arrange
        $this->createFile('conflict1.php', '<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            use Minisite\Infrastructure\Versioning\Contracts\Migration;
            
            class ConflictTest1 implements Migration {
                public function version(): string { return "1.0.0"; }
                public function description(): string { return "First conflict"; }
                public function up(): void {}
                public function down(): void {}
            }
        ');

        $this->createFile('conflict2.php', '<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            use Minisite\Infrastructure\Versioning\Contracts\Migration;
            
            class ConflictTest2 implements Migration {
                public function version(): string { return "1.1.0"; }
                public function description(): string { return "Second conflict"; }
                public function up(): void {}
                public function down(): void {}
            }
        ');

        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act & Assert
        // This should handle the class name conflict gracefully
        // The last loaded class will be used (PHP behavior)
        $result = $locator->all();

        // We expect at least one migration, but the exact behavior depends on PHP's class loading
        $this->assertGreaterThanOrEqual(1, count($result));
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
                public function up(): void {
                    global \$wpdb;
                    \$wpdb->query('{$upSql}');
                }
                public function down(): void {
                    global \$wpdb;
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
        // Convert _1_0_0_CreateBase to _1_0_0_CreateBase (keep underscores for class name)
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
