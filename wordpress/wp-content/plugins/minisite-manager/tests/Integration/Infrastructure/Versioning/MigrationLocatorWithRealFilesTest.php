<?php

namespace Tests\Integration\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\MigrationLocator;
use Minisite\Infrastructure\Versioning\Contracts\Migration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class MigrationLocatorWithRealFilesTest extends TestCase
{
    private string $testMigrationsDir;
    private DatabaseTestHelper $dbHelper;

    protected function setUp(): void
    {
        // Use the actual test migrations directory
        $this->testMigrationsDir = realpath(__DIR__ . '/../../../Support/TestMigrations');
        $this->dbHelper = new DatabaseTestHelper();
        
        // Clear any previously declared classes
        $this->clearDeclaredClasses();
    }

    protected function tearDown(): void
    {
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

    public function test_all_loads_real_test_migration_files(): void
    {
        // Arrange
        $this->assertTrue(is_dir($this->testMigrationsDir), "Test migrations directory should exist: {$this->testMigrationsDir}");
        $this->assertTrue(file_exists($this->testMigrationsDir . '/_1_0_0_TestInitial.php'), 'Test migration file should exist');
        
        $locator = new MigrationLocator($this->testMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertGreaterThanOrEqual(3, count($result), 'Should find at least 3 test migration files');
        
        // Verify all returned items are Migration instances
        foreach ($result as $migration) {
            $this->assertInstanceOf(Migration::class, $migration);
        }
        
        // Verify versions are sorted correctly
        $versions = array_map(fn($migration) => $migration->version(), $result);
        $sortedVersions = $versions;
        sort($sortedVersions, SORT_NATURAL);
        $this->assertEquals($sortedVersions, $versions, 'Migrations should be sorted by version');
    }

    public function test_all_returns_migrations_in_correct_order(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertGreaterThanOrEqual(3, count($result));
        
        // Check that we have the expected versions in order
        $versions = array_map(fn($migration) => $migration->version(), $result);
        
        // Should include our test versions
        $this->assertContains('1.0.0', $versions);
        $this->assertContains('1.1.0', $versions);
        $this->assertContains('2.0.0', $versions);
        
        // Verify ordering
        $index1_0_0 = array_search('1.0.0', $versions);
        $index1_1_0 = array_search('1.1.0', $versions);
        $index2_0_0 = array_search('2.0.0', $versions);
        
        $this->assertLessThan($index1_1_0, $index1_0_0, '1.0.0 should come before 1.1.0');
        $this->assertLessThan($index2_0_0, $index1_1_0, '1.1.0 should come before 2.0.0');
    }

    public function test_migrations_can_be_executed_successfully(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);
        $migrations = $locator->all();
        $wpdb = $this->dbHelper->getWpdb();

        // Act & Assert
        foreach ($migrations as $migration) {
            // Test that up() method can be called without errors
            $this->expectNotToPerformAssertions();
            $migration->up($wpdb);
        }
    }

    public function test_migrations_can_be_rolled_back(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);
        $migrations = $locator->all();
        $wpdb = $this->dbHelper->getWpdb();

        // Act & Assert
        foreach ($migrations as $migration) {
            // Test that down() method can be called without errors
            $this->expectNotToPerformAssertions();
            $migration->down($wpdb);
        }
    }

    public function test_migration_descriptions_are_meaningful(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        foreach ($result as $migration) {
            $description = $migration->description();
            $this->assertIsString($description);
            $this->assertNotEmpty($description);
            $this->assertStringContainsString('Test', $description, 'Test migration descriptions should contain "Test"');
        }
    }

    public function test_migration_versions_follow_semantic_versioning(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        foreach ($result as $migration) {
            $version = $migration->version();
            $this->assertIsString($version);
            $this->assertNotEmpty($version);
            
            // Basic semantic version validation (major.minor.patch)
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version, 
                "Version '{$version}' should follow semantic versioning pattern (major.minor.patch)");
        }
    }

    public function test_migration_interface_contract_is_fulfilled(): void
    {
        // Arrange
        $locator = new MigrationLocator($this->testMigrationsDir);

        // Act
        $result = $locator->all();

        // Assert
        foreach ($result as $migration) {
            // Verify all required methods exist and are callable
            $this->assertTrue(method_exists($migration, 'version'));
            $this->assertTrue(method_exists($migration, 'description'));
            $this->assertTrue(method_exists($migration, 'up'));
            $this->assertTrue(method_exists($migration, 'down'));
            
            $this->assertTrue(is_callable([$migration, 'version']));
            $this->assertTrue(is_callable([$migration, 'description']));
            $this->assertTrue(is_callable([$migration, 'up']));
            $this->assertTrue(is_callable([$migration, 'down']));
            
            // Verify return types
            $this->assertIsString($migration->version());
            $this->assertIsString($migration->description());
        }
    }

    public function test_migration_locator_handles_empty_directory_gracefully(): void
    {
        // Arrange
        $emptyDir = sys_get_temp_dir() . '/empty_migrations_' . uniqid();
        mkdir($emptyDir, 0755, true);
        $locator = new MigrationLocator($emptyDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Cleanup
        rmdir($emptyDir);
    }

    public function test_migration_locator_handles_non_existent_directory_gracefully(): void
    {
        // Arrange
        $nonExistentDir = '/non/existent/directory/' . uniqid();
        $locator = new MigrationLocator($nonExistentDir);

        // Act
        $result = $locator->all();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
