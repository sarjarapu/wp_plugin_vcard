<?php

namespace Minisite\Tests\Unit\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Testable subclass that exposes protected methods for testing
 */
class Testable_1_0_0_CreateBase extends _1_0_0_CreateBase
{
    public function publicLoadMinisiteFromJson(string $jsonFile, array $overrides = []): array
    {
        return $this->loadMinisiteFromJson($jsonFile, $overrides);
    }

    public function publicConvertLocationFormat(array $data): array
    {
        return $this->convertLocationFormat($data);
    }

    public function publicSetComputedFields(array $data): array
    {
        return $this->setComputedFields($data);
    }

    public function publicInsertMinisite(\wpdb $wpdb, array $minisiteData, string $name): string
    {
        return $this->insertMinisite($wpdb, $minisiteData, $name);
    }

    public function publicInsertReview(\wpdb $wpdb, string $minisiteId, string $authorName, float $rating, string $body, ?string $locale = 'en-US'): void
    {
        $this->insertReview($wpdb, $minisiteId, $authorName, $rating, $body, $locale);
    }

    public function publicAddForeignKeyIfNotExists(\wpdb $wpdb, string $table, string $constraintName, string $column, string $referencedTable, string $referencedColumn): void
    {
        $this->addForeignKeyIfNotExists($wpdb, $table, $constraintName, $column, $referencedTable, $referencedColumn);
    }

    public function publicSeedTestData(\wpdb $wpdb): void
    {
        $this->seedTestData($wpdb);
    }
}

/**
 * Unit tests for _1_0_0_CreateBase migration
 *
 */
#[RunTestsInSeparateProcesses]
class _1_0_0_CreateBaseTest extends TestCase
{
    private Testable_1_0_0_CreateBase $migration;
    private \wpdb $mockWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Define DB_NAME constant if not already defined
        if (!defined('DB_NAME')) {
            define('DB_NAME', 'test_database');
        }
        
        $this->migration = new Testable_1_0_0_CreateBase();
        $this->mockWpdb = $this->createMockWpdb();
    }

    /**
     * Test loading minisite data from JSON file
     */
    public function testLoadMinisiteFromJson(): void
    {
        $overrides = ['id' => 'test-id-123'];
        
        $result = $this->migration->publicLoadMinisiteFromJson('acme-dental.json', $overrides);
        
        $this->assertIsArray($result);
        $this->assertEquals('test-id-123', $result['id']);
        $this->assertEquals('acme-dental-dallas', $result['slug']);
        $this->assertEquals('Acme Dental', $result['name']);
        $this->assertEquals('Dallas', $result['city']);
        $this->assertEquals('US', $result['country_code']);
        $this->assertEquals('dentist', $result['industry']);
        $this->assertEquals('en-US', $result['default_locale']);
        $this->assertArrayHasKey('location_point', $result);
        $this->assertArrayHasKey('site_json', $result);
    }

    /**
     * Test location format conversion from JSON to database format
     */
    public function testConvertLocationFormat(): void
    {
        $data = [
            'location' => [
                'latitude' => 32.7767,
                'longitude' => -96.7970
            ],
            'other_field' => 'test'
        ];
        
        $result = $this->migration->publicConvertLocationFormat($data);
        
        $this->assertArrayNotHasKey('location', $result);
        $this->assertArrayHasKey('location_point', $result);
        $this->assertEquals(['longitude' => -96.7970, 'latitude' => 32.7767], $result['location_point']);
        $this->assertEquals('test', $result['other_field']);
    }

    /**
     * Test setting computed fields
     */
    public function testSetComputedFields(): void
    {
        $data = [
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'created_by' => null,
            'updated_by' => null
        ];
        
        $result = $this->migration->publicSetComputedFields($data);
        
        $this->assertArrayHasKey('slug', $result);
        $this->assertEquals('test-business-test-location', $result['slug']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('published_at', $result);
        $this->assertArrayHasKey('_minisite_current_version_id', $result);
        $this->assertNull($result['_minisite_current_version_id']);
    }

    /**
     * Test minisite insertion
     */
    public function testInsertMinisite(): void
    {
        $minisiteData = [
            'id' => 'test-minisite-123',
            'slug' => 'test-business-test-location',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Business — Test Location',
            'name' => 'Test Business',
            'city' => 'Test City',
            'region' => 'TC',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => ['longitude' => -96.7970, 'latitude' => 32.7767],
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'test',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 1,
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test business',
            'status' => 'published',
            'publish_status' => 'published',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
            'published_at' => '2025-01-01 00:00:00',
            'created_by' => 1,
            'updated_by' => 1,
            '_minisite_current_version_id' => null
        ];
        
        $result = $this->migration->publicInsertMinisite($this->mockWpdb, $minisiteData, 'TEST');
        
        $this->assertEquals('test-minisite-123', $result);
    }

    /**
     * Test review insertion
     */
    public function testInsertReview(): void
    {
        $this->migration->publicInsertReview(
            $this->mockWpdb,
            'test-minisite-123',
            'John Doe',
            4.5,
            'Great service!',
            'en-US'
        );
        
        // If we get here without exception, the method executed successfully
        $this->assertTrue(true);
    }

    /**
     * Test review insertion with custom locale
     */
    public function testInsertReviewWithCustomLocale(): void
    {
        $this->migration->publicInsertReview(
            $this->mockWpdb,
            'test-minisite-123',
            'Jane Smith',
            5.0,
            'Excellent experience!',
            'en-GB'
        );
        
        $this->assertTrue(true);
    }

    /**
     * Test version method returns correct version
     */
    public function testVersion(): void
    {
        $this->assertEquals('1.0.0', $this->migration->version());
    }

    /**
     * Test description method returns non-empty description
     */
    public function testDescription(): void
    {
        $description = $this->migration->description();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('minisites', $description);
    }

    /**
     * Test that JSON file loading throws exception for non-existent file
     */
    public function testLoadMinisiteFromJsonThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON file not found');
        
        $this->migration->publicLoadMinisiteFromJson('non-existent-file.json');
    }

    /**
     * Test addForeignKeyIfNotExists when constraint does not exist
     */
    public function testAddForeignKeyIfNotExistsWhenConstraintDoesNotExist(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        
        // Mock get_var to return 0 (constraint doesn't exist)
        $mockWpdb->method('get_var')->willReturn(0);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Mock query to return true (success)
        $mockWpdb->method('query')->willReturn(true);
        
        // Expect query to be called with ALTER TABLE statement
        $mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('ALTER TABLE'));
        
        $this->migration->publicAddForeignKeyIfNotExists(
            $mockWpdb,
            'wp_test_table',
            'fk_test_constraint',
            'test_column',
            'wp_referenced_table',
            'referenced_column'
        );
    }

    /**
     * Test addForeignKeyIfNotExists when constraint already exists
     */
    public function testAddForeignKeyIfNotExistsWhenConstraintExists(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        
        // Mock get_var to return 1 (constraint exists)
        $mockWpdb->method('get_var')->willReturn(1);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Expect query NOT to be called with ALTER TABLE statement
        $mockWpdb->expects($this->never())
            ->method('query');
        
        $this->migration->publicAddForeignKeyIfNotExists(
            $mockWpdb,
            'wp_test_table',
            'fk_test_constraint',
            'test_column',
            'wp_referenced_table',
            'referenced_column'
        );
    }

    /**
     * Test addForeignKeyIfNotExists with proper SQL query structure
     */
    public function testAddForeignKeyIfNotExistsSqlQueryStructure(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        
        // Mock get_var to return 0 (constraint doesn't exist)
        $mockWpdb->method('get_var')->willReturn(0);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Capture the actual query that gets executed
        $actualQuery = '';
        $mockWpdb->method('query')->willReturnCallback(function($query) use (&$actualQuery) {
            $actualQuery = $query;
            return true;
        });
        
        $this->migration->publicAddForeignKeyIfNotExists(
            $mockWpdb,
            'wp_test_table',
            'fk_test_constraint',
            'test_column',
            'wp_referenced_table',
            'referenced_column'
        );
        
        // Verify the query structure
        $this->assertStringContainsString('ALTER TABLE wp_test_table', $actualQuery);
        $this->assertStringContainsString('ADD CONSTRAINT fk_test_constraint', $actualQuery);
        $this->assertStringContainsString('FOREIGN KEY (test_column)', $actualQuery);
        $this->assertStringContainsString('REFERENCES wp_referenced_table(referenced_column)', $actualQuery);
        $this->assertStringContainsString('ON DELETE CASCADE', $actualQuery);
    }

    /**
     * Test seedTestData when no existing data exists (fresh seeding)
     */
    public function testSeedTestDataFreshSeeding(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Mock get_var to return 0 (no existing data)
        $mockWpdb->method('get_var')->willReturn(0);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Mock query to return true (success)
        $mockWpdb->method('query')->willReturn(true);
        
        // Mock insert to return true and set insert_id
        $mockWpdb->method('insert')->willReturn(true);
        $mockWpdb->insert_id = 123;
        
        // Mock get_row to return sample minisite data
        $mockWpdb->method('get_row')->willReturn([
            'id' => 'test-minisite-123',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'site_json' => '{"test": "data"}'
        ]);
        
        // Expect multiple insert calls (minisites + versions + reviews)
        $mockWpdb->expects($this->atLeast(4))->method('insert');
        
        // Expect multiple query calls (for version updates)
        $mockWpdb->expects($this->atLeast(4))->method('query');
        
        $this->migration->publicSeedTestData($mockWpdb);
    }

    /**
     * Test seedTestData when existing data already exists (skip seeding)
     */
    public function testSeedTestDataExistingData(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Mock get_var to return 1 (existing data found)
        $mockWpdb->method('get_var')->willReturn(1);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Expect no insert calls since seeding should be skipped
        $mockWpdb->expects($this->never())->method('insert');
        
        // Expect no query calls for version updates
        $mockWpdb->expects($this->never())->method('query');
        
        $this->migration->publicSeedTestData($mockWpdb);
    }

    /**
     * Test seedTestData duplicate check query structure
     */
    public function testSeedTestDataDuplicateCheckQuery(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Capture the duplicate check query
        $duplicateCheckQuery = '';
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) use (&$duplicateCheckQuery) {
            if (strpos($query, 'SELECT COUNT(*)') !== false) {
                $duplicateCheckQuery = $query;
            }
            return $query;
        });
        
        // Mock get_var to return 0 (no existing data)
        $mockWpdb->method('get_var')->willReturn(0);
        
        // Mock other methods
        $mockWpdb->method('query')->willReturn(true);
        $mockWpdb->method('insert')->willReturn(true);
        $mockWpdb->insert_id = 123;
        $mockWpdb->method('get_row')->willReturn([
            'id' => 'test-minisite-123',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'site_json' => '{"test": "data"}'
        ]);
        
        $this->migration->publicSeedTestData($mockWpdb);
        
        // Verify the duplicate check query structure (uses placeholders, not actual values)
        $this->assertStringContainsString('SELECT COUNT(*)', $duplicateCheckQuery);
        $this->assertStringContainsString('FROM wp_minisites', $duplicateCheckQuery);
        $this->assertStringContainsString('WHERE (business_slug=%s AND location_slug=%s)', $duplicateCheckQuery);
        $this->assertStringContainsString('OR (business_slug=%s AND location_slug=%s)', $duplicateCheckQuery);
        $this->assertStringContainsString('OR (business_slug=%s AND location_slug=%s)', $duplicateCheckQuery);
        $this->assertStringContainsString('OR (business_slug=%s AND location_slug=%s)', $duplicateCheckQuery);
    }

    /**
     * Test up() method structure and foreign key calls
     * Note: Cannot test full execution due to SqlLoader WordPress dependency
     */
    public function testUpMethodStructure(): void
    {
        // Test that up() method exists and is callable
        $this->assertTrue(method_exists($this->migration, 'up'));
        $this->assertTrue(is_callable([$this->migration, 'up']));
        
        // Test that up() method has the correct signature
        $reflection = new \ReflectionMethod($this->migration, 'up');
        $this->assertEquals(1, $reflection->getNumberOfParameters());
        $this->assertEquals('wpdb', $reflection->getParameters()[0]->getType()->getName());
    }

    /**
     * Test down() method calls all required cleanup operations
     */
    public function testDownMethodCalls(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Mock query to return true (success)
        $mockWpdb->method('query')->willReturn(true);
        
        // Expect exactly 8 query calls for dropping tables and event
        $mockWpdb->expects($this->exactly(8))->method('query');
        
        // Capture the queries to verify they're correct
        $queries = [];
        $mockWpdb->method('query')->willReturnCallback(function($query) use (&$queries) {
            $queries[] = $query;
            return true;
        });
        
        $this->migration->down($mockWpdb);
        
        // Verify the cleanup queries
        $this->assertStringContainsString('DROP EVENT IF EXISTS wp_minisite_purge_reservations_event', $queries[0]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_reservations', $queries[1]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_payment_history', $queries[2]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_payments', $queries[3]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_bookmarks', $queries[4]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_reviews', $queries[5]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisite_versions', $queries[6]);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_minisites', $queries[7]);
    }

    /**
     * Test insertMinisite with different data types and edge cases
     */
    public function testInsertMinisiteEdgeCases(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Mock query to return true (success)
        $mockWpdb->method('query')->willReturn(true);
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Mock get_row to return sample data
        $mockWpdb->method('get_row')->willReturn([
            'id' => 'test-minisite-456',
            'business_slug' => 'test-business-2',
            'location_slug' => 'test-location-2',
            'status' => 'draft',
            '_minisite_current_version_id' => 456
        ]);
        
        // Test with different data types
        $minisiteData = [
            'id' => 'test-minisite-456',
            'slug' => 'test-business-2-test-location-2',
            'business_slug' => 'test-business-2',
            'location_slug' => 'test-location-2',
            'title' => 'Test Business 2 — Test Location 2',
            'name' => 'Test Business 2',
            'city' => 'Test City 2',
            'region' => 'TC2',
            'country_code' => 'CA',
            'postal_code' => 'K1A 0A6',
            'location_point' => ['longitude' => -75.6972, 'latitude' => 45.4215],
            'site_template' => 'v2025',
            'palette' => 'green',
            'industry' => 'technology',
            'default_locale' => 'en-CA',
            'schema_version' => 2,
            'site_version' => 2,
            'site_json' => '{"test": "data2"}',
            'search_terms' => 'test business 2',
            'status' => 'draft',
            'publish_status' => 'draft',
            'created_at' => '2025-01-02 00:00:00',
            'updated_at' => '2025-01-02 00:00:00',
            'published_at' => null,
            'created_by' => 2,
            'updated_by' => 2,
            '_minisite_current_version_id' => 456
        ];
        
        $result = $this->migration->publicInsertMinisite($mockWpdb, $minisiteData, 'TEST2');
        
        $this->assertEquals('test-minisite-456', $result);
    }

    /**
     * Test insertMinisite SQL query structure
     */
    public function testInsertMinisiteSqlStructure(): void
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        $mockWpdb->prefix = 'wp_';
        
        // Capture the actual query that gets executed
        $actualQuery = '';
        $mockWpdb->method('query')->willReturnCallback(function($query) use (&$actualQuery) {
            $actualQuery = $query;
            return true;
        });
        
        // Mock prepare to return the query as-is
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Mock get_row to return sample data
        $mockWpdb->method('get_row')->willReturn([
            'id' => 'test-minisite-789',
            'business_slug' => 'test-business-3',
            'location_slug' => 'test-location-3',
            'status' => 'published',
            '_minisite_current_version_id' => null
        ]);
        
        $minisiteData = [
            'id' => 'test-minisite-789',
            'slug' => 'test-business-3-test-location-3',
            'business_slug' => 'test-business-3',
            'location_slug' => 'test-location-3',
            'title' => 'Test Business 3 — Test Location 3',
            'name' => 'Test Business 3',
            'city' => 'Test City 3',
            'region' => 'TC3',
            'country_code' => 'GB',
            'postal_code' => 'SW1A 1AA',
            'location_point' => ['longitude' => -0.1276, 'latitude' => 51.5074],
            'site_template' => 'v2025',
            'palette' => 'red',
            'industry' => 'finance',
            'default_locale' => 'en-GB',
            'schema_version' => 1,
            'site_version' => 1,
            'site_json' => '{"test": "data3"}',
            'search_terms' => 'test business 3',
            'status' => 'published',
            'publish_status' => 'published',
            'created_at' => '2025-01-03 00:00:00',
            'updated_at' => '2025-01-03 00:00:00',
            'published_at' => '2025-01-03 00:00:00',
            'created_by' => 3,
            'updated_by' => 3,
            '_minisite_current_version_id' => null
        ];
        
        $this->migration->publicInsertMinisite($mockWpdb, $minisiteData, 'TEST3');
        
        // Verify the SQL query structure (normalize whitespace for comparison)
        $normalizedQuery = preg_replace('/\s+/', ' ', trim($actualQuery));
        
        $this->assertStringContainsString('INSERT INTO wp_minisites', $normalizedQuery);
        $this->assertStringContainsString('id, slug, business_slug, location_slug', $normalizedQuery);
        $this->assertStringContainsString('title, name, city, region', $normalizedQuery);
        $this->assertStringContainsString('country_code, postal_code, location_point', $normalizedQuery);
        $this->assertStringContainsString('site_template, palette, industry', $normalizedQuery);
        $this->assertStringContainsString('default_locale, schema_version, site_version', $normalizedQuery);
        $this->assertStringContainsString('site_json, search_terms, status', $normalizedQuery);
        $this->assertStringContainsString('publish_status, created_at, updated_at', $normalizedQuery);
        $this->assertStringContainsString('published_at, created_by, updated_by', $normalizedQuery);
        $this->assertStringContainsString('_minisite_current_version_id', $normalizedQuery);
        $this->assertStringContainsString('POINT(', $normalizedQuery);
    }

    /**
     * Test that JSON file loading throws exception for invalid JSON
     */
    public function testLoadMinisiteFromJsonThrowsExceptionForInvalidJson(): void
    {
        // Create a temporary invalid JSON file in the minisites directory
        $tempFile = __DIR__ . '/../../../../../data/json/minisites/invalid-test.json';
        file_put_contents($tempFile, '{"invalid": json}');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        
        try {
            $this->migration->publicLoadMinisiteFromJson('invalid-test.json');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Create a mock wpdb object for testing
     */
    private function createMockWpdb(): \wpdb
    {
        $mockWpdb = $this->createMock(\wpdb::class);
        
        // Mock the prefix property
        $mockWpdb->prefix = 'wp_';
        
        // Mock the query method to return true (success)
        $mockWpdb->method('query')->willReturn(true);
        
        // Mock the prepare method to return the query as-is for testing
        $mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });
        
        // Mock the insert method to return true (success)
        $mockWpdb->method('insert')->willReturn(true);
        
        // Mock the get_row method for debug queries
        $mockWpdb->method('get_row')->willReturn((object)[
            'id' => 'test-minisite-123',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'status' => 'published',
            '_minisite_current_version_id' => null
        ]);
        
        return $mockWpdb;
    }
}
