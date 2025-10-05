<?php

namespace Tests\Unit\Features\MinisiteDisplay\WordPress;

use Minisite\Features\MinisiteDisplay\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\TestCase;

/**
 * Test WordPressMinisiteManager
 * 
 * Tests the WordPressMinisiteManager with mocked WordPress functions
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressMinisiteManagerTest extends TestCase
{
    private WordPressMinisiteManager $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = new WordPressMinisiteManager();
    }

    /**
     * Test findMinisiteBySlugs with existing minisite
     */
    public function test_find_minisite_by_slugs_with_existing_minisite(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock database result
        $mockResult = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        $this->mockDatabaseQuery($mockResult);

        $result = $this->wordPressManager->findMinisiteBySlugs('coffee-shop', 'downtown');

        $this->assertEquals($mockResult, $result);
    }

    /**
     * Test findMinisiteBySlugs with non-existing minisite
     */
    public function test_find_minisite_by_slugs_with_non_existing_minisite(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $this->mockDatabaseQuery(null);

        $result = $this->wordPressManager->findMinisiteBySlugs('nonexistent', 'location');

        $this->assertNull($result);
    }

    /**
     * Test findMinisiteBySlugs with empty slugs
     */
    public function test_find_minisite_by_slugs_with_empty_slugs(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $this->mockDatabaseQuery(null);

        $result = $this->wordPressManager->findMinisiteBySlugs('', '');

        $this->assertNull($result);
    }

    /**
     * Test findMinisiteBySlugs with special characters
     */
    public function test_find_minisite_by_slugs_with_special_characters(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock database result
        $mockResult = (object)[
            'id' => '456',
            'name' => 'Café & Restaurant',
            'business_slug' => 'café-&-restaurant',
            'location_slug' => 'main-street-123'
        ];

        $this->mockDatabaseQuery($mockResult);

        $result = $this->wordPressManager->findMinisiteBySlugs('café-&-restaurant', 'main-street-123');

        $this->assertEquals($mockResult, $result);
    }

    /**
     * Test findMinisiteBySlugs with database error
     */
    public function test_find_minisite_by_slugs_with_database_error(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock database error
        $this->mockDatabaseQuery(new \WP_Error('db_error', 'Database connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error: Database connection failed');

        $this->wordPressManager->findMinisiteBySlugs('business', 'location');
    }

    /**
     * Test minisiteExists with existing minisite
     */
    public function test_minisite_exists_with_existing_minisite(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock database result
        $mockResult = (object)['count' => 1];
        $this->mockDatabaseQuery($mockResult);

        $result = $this->wordPressManager->minisiteExists('coffee-shop', 'downtown');

        $this->assertTrue($result);
    }

    /**
     * Test minisiteExists with non-existing minisite
     */
    public function test_minisite_exists_with_non_existing_minisite(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $mockResult = (object)['count' => 0];
        $this->mockDatabaseQuery($mockResult);

        $result = $this->wordPressManager->minisiteExists('nonexistent', 'location');

        $this->assertFalse($result);
    }

    /**
     * Test minisiteExists with empty slugs
     */
    public function test_minisite_exists_with_empty_slugs(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $mockResult = (object)['count' => 0];
        $this->mockDatabaseQuery($mockResult);

        $result = $this->wordPressManager->minisiteExists('', '');

        $this->assertFalse($result);
    }

    /**
     * Test minisiteExists with database error
     */
    public function test_minisite_exists_with_database_error(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock database error
        $this->mockDatabaseQuery(new \WP_Error('db_error', 'Database connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error: Database connection failed');

        $this->wordPressManager->minisiteExists('business', 'location');
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(0, $constructor->getNumberOfParameters());
    }

    /**
     * Test findMinisiteBySlugs with null slugs
     */
    public function test_find_minisite_by_slugs_with_null_slugs(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $this->mockDatabaseQuery(null);

        $result = $this->wordPressManager->findMinisiteBySlugs(null, null);

        $this->assertNull($result);
    }

    /**
     * Test findMinisiteBySlugs with mixed null/empty slugs
     */
    public function test_find_minisite_by_slugs_with_mixed_null_empty_slugs(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Mock empty database result
        $this->mockDatabaseQuery(null);

        $result = $this->wordPressManager->findMinisiteBySlugs('business', null);

        $this->assertNull($result);
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(): void
    {
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->prepare = function($query, ...$args) {
            return $query;
        };
        $wpdb->get_row = function($query) {
            return $this->getMockDatabaseResult();
        };
    }

    /**
     * Mock database query result
     */
    private function mockDatabaseQuery($result): void
    {
        global $wpdb;
        $wpdb->get_row = function($query) use ($result) {
            return $result;
        };
    }

    /**
     * Get mock database result for testing
     */
    private function getMockDatabaseResult()
    {
        return (object)[
            'id' => '123',
            'name' => 'Test Minisite',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location'
        ];
    }
}
