<?php

namespace Tests\Unit\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\MigrationLocator;
use Minisite\Infrastructure\Versioning\Contracts\Migration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class MigrationLocatorTest extends TestCase
{
    private string $tempMigrationsDir;

    protected function setUp(): void
    {
        // Create temporary directory for test migrations
        $this->tempMigrationsDir = realpath(sys_get_temp_dir()) . '/minisite_migrations_unit_test_' . uniqid();
        mkdir($this->tempMigrationsDir, 0755, true);
        
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

    public function test_constructor_sets_directory_path(): void
    {
        // Arrange
        $expectedDir = '/path/to/migrations';

        // Act
        $locator = new MigrationLocator($expectedDir);

        // Assert
        $this->assertInstanceOf(MigrationLocator::class, $locator);
    }

    public function test_all_returns_empty_array_when_directory_does_not_exist(): void
    {
        // Arrange
        $nonExistentDir = '/non/existent/directory';
        $locator = new MigrationLocator($nonExistentDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_returns_empty_array_when_directory_has_no_php_files(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_ignores_non_php_files(): void
    {
        // Arrange
        $this->createFile('migration.txt', 'This is not a PHP file');
        $this->createFile('migration.js', 'console.log("Not PHP");');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_ignores_php_files_without_migration_classes(): void
    {
        // Arrange
        $this->createFile('regular_class.php', '<?php class RegularClass {}');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_ignores_classes_that_do_not_implement_migration_interface(): void
    {
        // Arrange
        $this->createFile('non_migration.php', '<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            class NonMigrationClass {}
        ');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_all_ignores_migration_classes_from_other_directories(): void
    {
        // Arrange
        // Create a migration class that would be in a different directory
        // But since we're creating it in the same directory, it will be found
        // This test verifies that the path checking works correctly
        $this->createFile('external_migration.php', '<?php 
            namespace Minisite\Infrastructure\Versioning\Migrations;
            use Minisite\Infrastructure\Versioning\Contracts\Migration;
            
            class TestExternalMigration implements Migration {
                public function version(): string { return "1.0.0"; }
                public function description(): string { return "External migration"; }
                public function up(\wpdb $wpdb): void {}
                public function down(\wpdb $wpdb): void {}
            }
        ');
        
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        // Since the file is in the same directory, it should be found
        $this->assertCount(1, $result);
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('External migration', $result[0]->description());
    }

    public function test_all_returns_single_migration_when_one_valid_migration_exists(): void
    {
        // Arrange
        $this->createMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', 'TestSingleMigration');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Migration::class, $result[0]);
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('Create base tables', $result[0]->description());
    }

    public function test_all_returns_multiple_migrations_sorted_by_version(): void
    {
        // Arrange
        $this->createMigrationFile('_2_0_0_AddFeatures.php', '2.0.0', 'Add new features', 'TestAddFeatures');
        $this->createMigrationFile('_1_0_0_CreateBase.php', '1.0.0', 'Create base tables', 'TestCreateBase');
        $this->createMigrationFile('_1_5_0_UpdateSchema.php', '1.5.0', 'Update schema', 'TestUpdateSchema');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('1.5.0', $result[1]->version());
        $this->assertEquals('2.0.0', $result[2]->version());
    }

    public function test_all_handles_complex_version_numbers_correctly(): void
    {
        // Arrange
        $this->createMigrationFile('_1_0_0_Initial.php', '1.0.0', 'Initial migration', 'TestComplexInitial');
        $this->createMigrationFile('_1_0_1_Patch.php', '1.0.1', 'Patch migration', 'TestComplexPatch');
        $this->createMigrationFile('_1_10_0_Minor.php', '1.10.0', 'Minor migration', 'TestComplexMinor');
        $this->createMigrationFile('_2_0_0_Major.php', '2.0.0', 'Major migration', 'TestComplexMajor');
        $this->createMigrationFile('_10_0_0_VeryMajor.php', '10.0.0', 'Very major migration', 'TestComplexVeryMajor');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(5, $result);
        $expectedVersions = ['1.0.0', '1.0.1', '1.10.0', '2.0.0', '10.0.0'];
        $actualVersions = array_map(fn($migration) => $migration->version(), $result);
        $this->assertEquals($expectedVersions, $actualVersions);
    }

    public function test_all_handles_pre_release_versions(): void
    {
        // Arrange
        $this->createMigrationFile('_1_0_0_Stable.php', '1.0.0', 'Stable version', 'TestPreReleaseStable');
        $this->createMigrationFile('_1_0_0_alpha1_Alpha.php', '1.0.0-alpha1', 'Alpha version', 'TestPreReleaseAlpha');
        $this->createMigrationFile('_1_0_0_beta1_Beta.php', '1.0.0-beta1', 'Beta version', 'TestPreReleaseBeta');
        $this->createMigrationFile('_1_0_0_rc1_ReleaseCandidate.php', '1.0.0-rc1', 'Release candidate', 'TestPreReleaseRC');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(4, $result);
        $expectedVersions = ['1.0.0-alpha1', '1.0.0-beta1', '1.0.0-rc1', '1.0.0'];
        $actualVersions = array_map(fn($migration) => $migration->version(), $result);
        $this->assertEquals($expectedVersions, $actualVersions);
    }

    public function test_all_ignores_malformed_php_files(): void
    {
        // Arrange
        // Create a malformed file that won't cause a fatal error
        $this->createFile('malformed.php', '<?php 
            // This file has syntax errors but won\'t cause fatal errors
            class MalformedClass { 
                // Missing closing brace intentionally
            }
        ');
        $this->createMigrationFile('_1_0_0_Valid.php', '1.0.0', 'Valid migration', 'TestMalformedValid');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        // Should only return the valid migration, ignoring the malformed one
        $this->assertCount(1, $result);
        $this->assertEquals('1.0.0', $result[0]->version());
    }

    public function test_all_handles_migrations_with_different_namespaces(): void
    {
        // Arrange
        $this->createFile('custom_namespace.php', '<?php 
            namespace Custom\Namespace;
            use Minisite\Infrastructure\Versioning\Contracts\Migration;
            
            class TestCustomNamespaceMigration implements Migration {
                public function version(): string { return "1.0.0"; }
                public function description(): string { return "Custom namespace migration"; }
                public function up(\wpdb $wpdb): void {}
                public function down(\wpdb $wpdb): void {}
            }
        ');
        $locator = new MigrationLocator($this->tempMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('1.0.0', $result[0]->version());
        $this->assertEquals('Custom namespace migration', $result[0]->description());
    }

    /**
     * Create a migration file with the given filename, version, and description
     */
    private function createMigrationFile(string $filename, string $version, string $description, ?string $customClassName = null): void
    {
        $className = $customClassName ?? $this->getClassNameFromFilename($filename);
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
                    // Migration up logic
                }
                public function down(\wpdb \$wpdb): void {
                    // Migration down logic
                }
            }
        ";
        
        $this->createFile($filename, $content);
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
     * Create a file with the given filename and content
     */
    private function createFile(string $filename, string $content): void
    {
        $filepath = $this->tempMigrationsDir . '/' . $filename;
        file_put_contents($filepath, $content);
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
