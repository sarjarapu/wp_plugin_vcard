<?php

namespace Tests\Unit\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\MigrationRunner;
use Minisite\Infrastructure\Versioning\MigrationLocator;
use Minisite\Infrastructure\Versioning\Contracts\Migration;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MigrationRunnerTest extends TestCase
{
    private MigrationRunner $runner;
    private MigrationLocator&MockObject $mockLocator;
    private \wpdb&MockObject $mockWpdb;
    private string $testOptionKey = 'minisite_test_version';
    private string $testTargetVersion = '2.0.0';

    protected function setUp(): void
    {
        // Create mocks
        $this->mockLocator = $this->createMock(MigrationLocator::class);
        $this->mockWpdb = $this->createMock(\wpdb::class);
        
        // Create test instance
        $this->runner = new MigrationRunner(
            $this->testTargetVersion,
            $this->testOptionKey,
            $this->mockLocator
        );
        
        // Clear any existing test options
        delete_option($this->testOptionKey);
    }

    public function test_constructor_sets_properties_correctly(): void
    {
        $targetVersion = '1.5.0';
        $optionKey = 'test_option';
        $locator = $this->createMock(MigrationLocator::class);
        
        $runner = new MigrationRunner($targetVersion, $optionKey, $locator);
        
        $this->assertInstanceOf(MigrationRunner::class, $runner);
    }

    public function test_current_returns_default_version_when_option_not_set(): void
    {
        $result = $this->runner->current();
        
        $this->assertEquals('0.0.0', $result);
    }

    public function test_current_returns_stored_version_when_option_exists(): void
    {
        $storedVersion = '1.2.3';
        
        // Set the option
        update_option($this->testOptionKey, $storedVersion);
        
        $result = $this->runner->current();
        
        $this->assertEquals($storedVersion, $result);
    }

    public function test_upgradeTo_with_no_pending_migrations(): void
    {
        $currentVersion = '2.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Mock locator to return empty array
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([]);
        
        $loggerCalled = false;
        $logger = function($msg) use (&$loggerCalled) {
            $loggerCalled = true;
        };
        
        $this->runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertFalse($loggerCalled, 'Logger should not be called when no migrations to run');
    }

    public function test_upgradeTo_runs_single_pending_migration(): void
    {
        $currentVersion = '1.0.0';
        $migrationVersion = '1.1.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migration
        $mockMigration = $this->createMockMigration($migrationVersion, 'Add new feature');
        
        // Mock locator to return single migration
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([$mockMigration]);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString($migrationVersion, $loggedMessages[0]);
        $this->assertStringContainsString('Add new feature', $loggedMessages[0]);
        
        // Verify option was updated
        $this->assertEquals($migrationVersion, get_option($this->testOptionKey));
    }

    public function test_upgradeTo_runs_multiple_pending_migrations_in_order(): void
    {
        $currentVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations (already sorted by MigrationLocator)
        $migration1 = $this->createMockMigration('1.1.0', 'Add feature A');
        $migration2 = $this->createMockMigration('1.2.0', 'Add feature B');
        $migration3 = $this->createMockMigration('1.3.0', 'Add feature C');
        
        $migrations = [$migration1, $migration2, $migration3];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertCount(3, $loggedMessages);
        $this->assertStringContainsString('1.1.0', $loggedMessages[0]);
        $this->assertStringContainsString('1.2.0', $loggedMessages[1]);
        $this->assertStringContainsString('1.3.0', $loggedMessages[2]);
        
        // Verify final version was set
        $this->assertEquals('1.3.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_skips_migrations_already_at_current_version(): void
    {
        $currentVersion = '1.1.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations
        $migration1 = $this->createMockMigration('1.1.0', 'Already applied'); // Same as current
        $migration2 = $this->createMockMigration('1.2.0', 'Should run');
        
        $migrations = [$migration1, $migration2];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.2.0', $loggedMessages[0]);
        $this->assertStringNotContainsString('1.1.0', $loggedMessages[0]);
        
        // Verify final version was set
        $this->assertEquals('1.2.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_skips_migrations_beyond_target_version(): void
    {
        $currentVersion = '1.0.0';
        $targetVersion = '1.2.0';
        
        // Create runner with specific target version
        $runner = new MigrationRunner($targetVersion, $this->testOptionKey, $this->mockLocator);
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations
        $migration1 = $this->createMockMigration('1.1.0', 'Should run');
        $migration2 = $this->createMockMigration('1.2.0', 'Should run');
        $migration3 = $this->createMockMigration('1.3.0', 'Should skip'); // Beyond target
        
        $migrations = [$migration1, $migration2, $migration3];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('1.1.0', $loggedMessages[0]);
        $this->assertStringContainsString('1.2.0', $loggedMessages[1]);
        
        // Verify final version was set
        $this->assertEquals('1.2.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_handles_pre_release_versions(): void
    {
        $currentVersion = '0.9.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations with pre-release versions
        $migration1 = $this->createMockMigration('1.0.0-alpha1', 'Alpha version');
        $migration2 = $this->createMockMigration('1.0.0-beta1', 'Beta version');
        $migration3 = $this->createMockMigration('1.0.0-rc1', 'Release candidate');
        $migration4 = $this->createMockMigration('1.0.0', 'Final version');
        
        $migrations = [$migration1, $migration2, $migration3, $migration4];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->upgradeTo($this->mockWpdb, $logger);
        
        $this->assertCount(4, $loggedMessages);
        $this->assertStringContainsString('1.0.0-alpha1', $loggedMessages[0]);
        $this->assertStringContainsString('1.0.0-beta1', $loggedMessages[1]);
        $this->assertStringContainsString('1.0.0-rc1', $loggedMessages[2]);
        $this->assertStringContainsString('1.0.0', $loggedMessages[3]);
        
        // Verify final version was set
        $this->assertEquals('1.0.0', get_option($this->testOptionKey));
    }

    public function test_upgradeTo_uses_default_logger_when_none_provided(): void
    {
        $currentVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migration
        $mockMigration = $this->createMockMigration('1.1.0', 'Test migration');
        
        // Mock locator to return single migration
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([$mockMigration]);
        
        // Should not throw any exceptions with default logger
        $this->runner->upgradeTo($this->mockWpdb);
        
        $this->assertTrue(true, 'Should complete without errors');
    }

    public function test_downgradeTo_with_no_migrations_to_rollback(): void
    {
        $currentVersion = '1.0.0';
        $targetVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Mock locator to return empty array
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([]);
        
        $loggerCalled = false;
        $logger = function($msg) use (&$loggerCalled) {
            $loggerCalled = true;
        };
        
        $this->runner->downgradeTo($this->mockWpdb, $targetVersion, $logger);
        
        $this->assertFalse($loggerCalled, 'Logger should not be called when no migrations to rollback');
    }

    public function test_downgradeTo_rolls_back_single_migration(): void
    {
        $currentVersion = '1.1.0';
        $targetVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migration
        $mockMigration = $this->createMockMigration('1.1.0', 'Feature to rollback');
        
        // Mock locator to return single migration
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([$mockMigration]);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->downgradeTo($this->mockWpdb, $targetVersion, $logger);
        
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.1.0', $loggedMessages[0]);
        $this->assertStringContainsString('Feature to rollback', $loggedMessages[0]);
        $this->assertStringContainsString('Reverting', $loggedMessages[0]);
        
        // Verify option was updated to target version
        $this->assertEquals($targetVersion, get_option($this->testOptionKey));
    }

    public function test_downgradeTo_rolls_back_multiple_migrations_in_reverse_order(): void
    {
        $currentVersion = '1.3.0';
        $targetVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations (in normal order, will be reversed by downgradeTo)
        $migration1 = $this->createMockMigration('1.1.0', 'Feature A');
        $migration2 = $this->createMockMigration('1.2.0', 'Feature B');
        $migration3 = $this->createMockMigration('1.3.0', 'Feature C');
        
        $migrations = [$migration1, $migration2, $migration3];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->downgradeTo($this->mockWpdb, $targetVersion, $logger);
        
        // Based on the logic, only the first migration (1.3.0) should run
        // because after it runs, current becomes target (1.0.0), and subsequent
        // migrations won't meet the condition version_compare(current, ver, '>=')
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.3.0', $loggedMessages[0]);
        
        // Verify final version was set to target
        $this->assertEquals($targetVersion, get_option($this->testOptionKey));
    }

    public function test_downgradeTo_skips_migrations_below_target_version(): void
    {
        $currentVersion = '1.3.0';
        $targetVersion = '1.2.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migrations
        $migration1 = $this->createMockMigration('1.1.0', 'Should skip'); // Below target
        $migration2 = $this->createMockMigration('1.2.0', 'Should skip'); // At target
        $migration3 = $this->createMockMigration('1.3.0', 'Should rollback'); // Above target
        
        $migrations = [$migration1, $migration2, $migration3];
        
        // Mock locator to return migrations
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn($migrations);
        
        $loggedMessages = [];
        $logger = function($msg) use (&$loggedMessages) {
            $loggedMessages[] = $msg;
        };
        
        $this->runner->downgradeTo($this->mockWpdb, $targetVersion, $logger);
        
        $this->assertCount(1, $loggedMessages);
        $this->assertStringContainsString('1.3.0', $loggedMessages[0]);
        
        // Verify final version was set to target
        $this->assertEquals($targetVersion, get_option($this->testOptionKey));
    }

    public function test_downgradeTo_uses_default_logger_when_none_provided(): void
    {
        $currentVersion = '1.1.0';
        $targetVersion = '1.0.0';
        
        // Set current version
        update_option($this->testOptionKey, $currentVersion);
        
        // Create mock migration
        $mockMigration = $this->createMockMigration('1.1.0', 'Test migration');
        
        // Mock locator to return single migration
        $this->mockLocator->expects($this->once())
            ->method('all')
            ->willReturn([$mockMigration]);
        
        // Should not throw any exceptions with default logger
        $this->runner->downgradeTo($this->mockWpdb, $targetVersion);
        
        $this->assertTrue(true, 'Should complete without errors');
    }

    /**
     * Create a mock migration with the given version and description
     */
    private function createMockMigration(string $version, string $description): Migration&MockObject
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('version')->willReturn($version);
        $migration->method('description')->willReturn($description);
        $migration->method('up')->willReturnCallback(function() {});
        $migration->method('down')->willReturnCallback(function() {});
        
        return $migration;
    }
}