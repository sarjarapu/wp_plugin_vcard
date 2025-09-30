<?php

namespace Minisite\Tests\Integration\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase;
use Tests\Support\DatabaseTestHelper;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for _1_0_0_CreateBase migration
 * 
 * These tests run the actual migration with a real database to verify:
 * - Table creation with proper schemas
 * - Foreign key constraints
 * - MySQL events
 * - Data seeding
 * - Rollback functionality
 */
class _1_0_0_CreateBaseIntegrationTest extends TestCase
{
    private DatabaseTestHelper $dbHelper;
    private _1_0_0_CreateBase $migration;
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Check if we're in a WordPress environment
        if (!$this->isWordPressEnvironment()) {
            $this->markTestSkipped('Integration tests require WordPress environment with wp-admin/includes/upgrade.php');
        }
        
        // Set up database helper
        $this->dbHelper = new DatabaseTestHelper();
        $this->wpdb = $this->dbHelper->getWpdb();
        $this->migration = new _1_0_0_CreateBase();
        
        // Clean up any existing test data
        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        // Clean up after each test (only if wpdb was initialized)
        if (isset($this->wpdb)) {
            $this->cleanupTestTables();
        }
        parent::tearDown();
    }

    /**
     * Test that the up() method creates all required tables
     */
    public function testUpCreatesAllRequiredTables(): void
    {
        // Act - Run the migration
        $this->migration->up($this->wpdb);

        // Assert - Verify all tables were created
        $expectedTables = [
            'minisites',
            'minisite_versions', 
            'minisite_reviews',
            'minisite_bookmarks',
            'minisite_payments',
            'minisite_payment_history',
            'minisite_reservations'
        ];

        foreach ($expectedTables as $table) {
            $fullTableName = $this->wpdb->prefix . $table;
            $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '{$fullTableName}'");
            $this->assertNotNull($tableExists, "Table {$fullTableName} should exist");
        }
    }

    /**
     * Test that the up() method creates tables with proper schemas
     */
    public function testUpCreatesTablesWithProperSchemas(): void
    {
        // Act - Run the migration
        $this->migration->up($this->wpdb);

        // Assert - Verify minisites table has required columns
        $minisitesTable = $this->wpdb->prefix . 'minisites';
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$minisitesTable}");
        $columnNames = array_column($columns, 'Field');

        $expectedColumns = [
            'id', 'slug', 'business_slug', 'location_slug', 'title', 'name',
            'city', 'region', 'country_code', 'postal_code', 'location_point',
            'site_template', 'palette', 'industry', 'default_locale',
            'schema_version', 'site_version', 'site_json', 'search_terms',
            'status', 'publish_status', 'created_at', 'updated_at',
            'published_at', 'created_by', 'updated_by', '_minisite_current_version_id'
        ];

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains($expectedColumn, $columnNames, "Column {$expectedColumn} should exist in minisites table");
        }
    }

    /**
     * Test that the up() method creates foreign key constraints
     */
    public function testUpCreatesForeignKeyConstraints(): void
    {
        // Act - Run the migration
        $this->migration->up($this->wpdb);

        // Assert - Verify foreign key constraints exist
        $constraints = $this->wpdb->get_results("
            SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND REFERENCED_TABLE_NAME IS NOT NULL
            AND TABLE_NAME LIKE '{$this->wpdb->prefix}minisite%'
        ");

        $this->assertNotEmpty($constraints, 'Foreign key constraints should be created');
        
        // Verify specific foreign key constraints
        $constraintNames = array_column($constraints, 'CONSTRAINT_NAME');
        $expectedConstraints = [
            'fk_versions_minisite_id',
            'fk_reviews_minisite_id', 
            'fk_bookmarks_minisite_id',
            'fk_payments_minisite_id',
            'fk_payment_history_minisite_id',
            'fk_payment_history_payment_id',
            'fk_reservations_minisite_id'
        ];

        foreach ($expectedConstraints as $expectedConstraint) {
            $this->assertContains($expectedConstraint, $constraintNames, "Foreign key constraint {$expectedConstraint} should exist");
        }
    }

    /**
     * Test that the up() method creates MySQL event for cleanup
     */
    public function testUpCreatesCleanupEvent(): void
    {
        // Act - Run the migration
        $this->migration->up($this->wpdb);

        // Assert - Verify cleanup event exists
        $events = $this->wpdb->get_results("
            SELECT EVENT_NAME 
            FROM information_schema.EVENTS 
            WHERE EVENT_SCHEMA = DATABASE() 
            AND EVENT_NAME = 'cleanup_expired_reservations'
        ");

        $this->assertNotEmpty($events, 'Cleanup event should be created');
    }

    /**
     * Test that the up() method seeds test data
     */
    public function testUpSeedsTestData(): void
    {
        // Act - Run the migration
        $this->migration->up($this->wpdb);

        // Assert - Verify test data was seeded
        $minisitesTable = $this->wpdb->prefix . 'minisites';
        $minisiteCount = $this->wpdb->get_var("SELECT COUNT(*) FROM {$minisitesTable}");
        
        $this->assertGreaterThan(0, $minisiteCount, 'Test data should be seeded');

        // Verify specific seeded minisites exist
        $seededMinisites = $this->wpdb->get_results("
            SELECT business_slug, location_slug 
            FROM {$minisitesTable} 
            WHERE business_slug IN ('acme-dental', 'lotus-textiles', 'green-bites', 'swift-transit')
        ");

        $this->assertCount(4, $seededMinisites, 'Should have 4 seeded minisites');

        // Verify reviews were seeded
        $reviewsTable = $this->wpdb->prefix . 'minisite_reviews';
        $reviewCount = $this->wpdb->get_var("SELECT COUNT(*) FROM {$reviewsTable}");
        $this->assertGreaterThan(0, $reviewCount, 'Reviews should be seeded');
    }

    /**
     * Test that the down() method properly cleans up all tables and events
     */
    public function testDownCleansUpAllTablesAndEvents(): void
    {
        // Arrange - First run up() to create everything
        $this->migration->up($this->wpdb);

        // Verify tables exist before cleanup
        $minisitesTable = $this->wpdb->prefix . 'minisites';
        $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '{$minisitesTable}'");
        $this->assertNotNull($tableExists, 'Table should exist before cleanup');

        // Act - Run the down migration
        $this->migration->down($this->wpdb);

        // Assert - Verify all tables were dropped
        $expectedTables = [
            'minisites',
            'minisite_versions', 
            'minisite_reviews',
            'minisite_bookmarks',
            'minisite_payments',
            'minisite_payment_history',
            'minisite_reservations'
        ];

        foreach ($expectedTables as $table) {
            $fullTableName = $this->wpdb->prefix . $table;
            $tableExists = $this->wpdb->get_var("SHOW TABLES LIKE '{$fullTableName}'");
            $this->assertNull($tableExists, "Table {$fullTableName} should be dropped");
        }

        // Verify cleanup event was dropped
        $events = $this->wpdb->get_results("
            SELECT EVENT_NAME 
            FROM information_schema.EVENTS 
            WHERE EVENT_SCHEMA = DATABASE() 
            AND EVENT_NAME = 'cleanup_expired_reservations'
        ");

        $this->assertEmpty($events, 'Cleanup event should be dropped');
    }

    /**
     * Test that running up() multiple times is idempotent
     */
    public function testUpIsIdempotent(): void
    {
        // Act - Run the migration twice
        $this->migration->up($this->wpdb);
        $this->migration->up($this->wpdb);

        // Assert - Should not cause errors and data should be consistent
        $minisitesTable = $this->wpdb->prefix . 'minisites';
        $minisiteCount = $this->wpdb->get_var("SELECT COUNT(*) FROM {$minisitesTable}");
        
        // Should have seeded data (not duplicated)
        $this->assertGreaterThan(0, $minisiteCount, 'Should have seeded data');
        $this->assertLessThanOrEqual(4, $minisiteCount, 'Should not have duplicated seeded data');
    }

    /**
     * Test that the migration handles database errors gracefully
     */
    public function testMigrationHandlesDatabaseErrors(): void
    {
        // This test would require mocking database errors
        // For now, we'll test that the migration completes successfully
        $this->expectNotToPerformAssertions();
        
        try {
            $this->migration->up($this->wpdb);
            $this->migration->down($this->wpdb);
        } catch (\Exception $e) {
            $this->fail('Migration should handle database operations gracefully: ' . $e->getMessage());
        }
    }

    /**
     * Check if we're in a WordPress environment
     */
    private function isWordPressEnvironment(): bool
    {
        // Check if WordPress functions exist
        if (!function_exists('current_time') || !function_exists('get_current_user_id')) {
            return false;
        }
        
        // Check if wp-admin/includes/upgrade.php exists
        $upgradeFile = __DIR__ . '/../../../../../wp-admin/includes/upgrade.php';
        if (!file_exists($upgradeFile)) {
            return false;
        }
        
        // Check if SQL files exist
        $sqlDir = __DIR__ . '/../../../../../data/db/tables/';
        if (!is_dir($sqlDir)) {
            return false;
        }
        
        return true;
    }

    /**
     * Clean up test tables and events
     */
    private function cleanupTestTables(): void
    {
        $testTables = [
            'minisites',
            'minisite_versions', 
            'minisite_reviews',
            'minisite_bookmarks',
            'minisite_payments',
            'minisite_payment_history',
            'minisite_reservations'
        ];
        
        foreach ($testTables as $table) {
            $fullTableName = $this->wpdb->prefix . $table;
            try {
                $this->wpdb->query("DROP TABLE IF EXISTS {$fullTableName}");
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }

        // Clean up events
        try {
            $this->wpdb->query("DROP EVENT IF EXISTS cleanup_expired_reservations");
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }
}
