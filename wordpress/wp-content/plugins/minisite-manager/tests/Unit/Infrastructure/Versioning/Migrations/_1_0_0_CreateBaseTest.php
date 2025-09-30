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
