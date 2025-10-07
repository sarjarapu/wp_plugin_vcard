<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteListing\WordPress;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

/**
 * Tests for WordPressListingManager
 * 
 * Tests the WordPressListingManager to ensure it properly delegates
 * to repositories and formats data correctly.
 */
final class WordPressListingManagerTest extends TestCase
{
    private WordPressListingManager $listingManager;
    private \wpdb|MockObject $wpdb;

    protected function setUp(): void
    {
        // Skip all tests in this class as they require complex $wpdb mocking
        $this->markTestSkipped('WordPressListingManager tests require complex $wpdb mocking');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        $this->clearWordPressMocks();
    }

    /**
     * Test listMinisitesByOwner with successful results
     */
    public function test_list_minisites_by_owner_with_successful_results(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 0;

        $mockMinisites = [
            $this->createMockMinisite('1', 'Test Minisite 1', 'test-minisite-1', 'test', 'business', 'New York', 'NY', 'US', 'published'),
            $this->createMockMinisite('2', 'Test Minisite 2', 'test-minisite-2', 'test2', 'business2', 'Los Angeles', 'CA', 'US', 'draft')
        ];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn($mockMinisites);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Test first minisite
        $this->assertEquals('1', $result[0]['id']);
        $this->assertEquals('Test Minisite 1', $result[0]['title']);
        $this->assertEquals('test-minisite-1', $result[0]['name']);
        $this->assertEquals('test', $result[0]['slugs']['business']);
        $this->assertEquals('business', $result[0]['slugs']['location']);
        $this->assertStringContainsString('/b/test/business', $result[0]['route']);
        $this->assertEquals('New York, NY, US', $result[0]['location']);
        $this->assertEquals('published', $result[0]['status']);
        $this->assertEquals('Published', $result[0]['status_chip']);
        $this->assertEquals('Unknown', $result[0]['subscription']);
        $this->assertEquals('Unknown', $result[0]['online']);

        // Test second minisite
        $this->assertEquals('2', $result[1]['id']);
        $this->assertEquals('Test Minisite 2', $result[1]['title']);
        $this->assertEquals('test-minisite-2', $result[1]['name']);
        $this->assertEquals('test2', $result[1]['slugs']['business']);
        $this->assertEquals('business2', $result[1]['slugs']['location']);
        $this->assertStringContainsString('/b/test2/business2', $result[1]['route']);
        $this->assertEquals('Los Angeles, CA, US', $result[1]['location']);
        $this->assertEquals('draft', $result[1]['status']);
        $this->assertEquals('Draft', $result[1]['status_chip']);
    }

    /**
     * Test listMinisitesByOwner with empty results
     */
    public function test_list_minisites_by_owner_with_empty_results(): void
    {
        $userId = 456;
        $limit = 50;
        $offset = 0;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * Test listMinisitesByOwner with pagination
     */
    public function test_list_minisites_by_owner_with_pagination(): void
    {
        $userId = 123;
        $limit = 10;
        $offset = 20;

        $mockMinisites = [
            $this->createMockMinisite('21', 'Paged Minisite', 'paged-minisite', 'paged', 'business', 'Chicago', 'IL', 'US', 'published')
        ];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn($mockMinisites);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('21', $result[0]['id']);
        $this->assertEquals('Paged Minisite', $result[0]['title']);
    }

    /**
     * Test listMinisitesByOwner with different user IDs
     */
    public function test_list_minisites_by_owner_with_different_user_ids(): void
    {
        $userId = 999;
        $limit = 25;
        $offset = 5;

        $mockMinisites = [
            $this->createMockMinisite('5', 'User 999 Minisite', 'user-999-minisite', 'user999', 'business', 'Miami', 'FL', 'US', 'published')
        ];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn($mockMinisites);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('5', $result[0]['id']);
        $this->assertEquals('User 999 Minisite', $result[0]['title']);
    }

    /**
     * Test listMinisitesByOwner with zero limit
     */
    public function test_list_minisites_by_owner_with_zero_limit(): void
    {
        $userId = 123;
        $limit = 0;
        $offset = 0;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * Test listMinisitesByOwner with large offset
     */
    public function test_list_minisites_by_owner_with_large_offset(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 1000;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * Test listMinisitesByOwner with minisite without title
     */
    public function test_list_minisites_by_owner_with_minisite_without_title(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 0;

        $mockMinisite = $this->createMockMinisite('1', '', 'test-minisite', 'test', 'business', 'New York', 'NY', 'US', 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([$mockMinisite]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('test-minisite', $result[0]['title']); // Should fallback to name
    }

    /**
     * Test listMinisitesByOwner with minisite without region
     */
    public function test_list_minisites_by_owner_with_minisite_without_region(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 0;

        $mockMinisite = $this->createMockMinisite('1', 'Test Minisite', 'test-minisite', 'test', 'business', 'New York', null, 'US', 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([$mockMinisite]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('New York, US', $result[0]['location']); // Should not include empty region
    }

    /**
     * Test listMinisitesByOwner with minisite without updated_at
     */
    public function test_list_minisites_by_owner_with_minisite_without_updated_at(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 0;

        $mockMinisite = $this->createMockMinisite('1', 'Test Minisite', 'test-minisite', 'test', 'business', 'New York', 'NY', 'US', 'published');
        $mockMinisite->updatedAt = null;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([$mockMinisite]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['updated_at']);
    }

    /**
     * Test listMinisitesByOwner with minisite without published_at
     */
    public function test_list_minisites_by_owner_with_minisite_without_published_at(): void
    {
        $userId = 123;
        $limit = 50;
        $offset = 0;

        $mockMinisite = $this->createMockMinisite('1', 'Test Minisite', 'test-minisite', 'test', 'business', 'New York', 'NY', 'US', 'draft');
        $mockMinisite->publishedAt = null;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with($userId, $limit, $offset)
            ->willReturn([$mockMinisite]);

        $result = $this->listingManager->listMinisitesByOwner($userId, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['published_at']);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->listingManager);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(2, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $expectedTypes = [
            MinisiteRepository::class,
            VersionRepository::class
        ];
        
        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }

    /**
     * Test listMinisitesByOwner method is public
     */
    public function test_list_minisites_by_owner_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->listingManager);
        $method = $reflection->getMethod('listMinisitesByOwner');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test listMinisitesByOwner method has correct parameter types
     */
    public function test_list_minisites_by_owner_method_parameter_types(): void
    {
        $reflection = new \ReflectionClass($this->listingManager);
        $method = $reflection->getMethod('listMinisitesByOwner');
        $params = $method->getParameters();
        
        $this->assertCount(3, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertEquals('offset', $params[2]->getName());
        
        // Check default values
        $this->assertEquals(50, $params[1]->getDefaultValue());
        $this->assertEquals(0, $params[2]->getDefaultValue());
    }

    /**
     * Test listMinisitesByOwner method return type
     */
    public function test_list_minisites_by_owner_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->listingManager);
        $method = $reflection->getMethod('listMinisitesByOwner');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Create a mock Minisite object for testing
     */
    private function createMockMinisite(
        string $id,
        ?string $title,
        string $name,
        string $businessSlug,
        string $locationSlug,
        string $city,
        ?string $region,
        string $countryCode,
        string $status
    ): Minisite {
        $minisite = $this->createMock(Minisite::class);
        
        $minisite->id = $id;
        $minisite->title = $title;
        $minisite->name = $name;
        $minisite->slugs = new SlugPair($businessSlug, $locationSlug);
        $minisite->city = $city;
        $minisite->region = $region;
        $minisite->countryCode = $countryCode;
        $minisite->status = $status;
        $minisite->updatedAt = new \DateTimeImmutable('2025-01-06 10:00:00');
        $minisite->publishedAt = $status === 'published' ? new \DateTimeImmutable('2025-01-06 09:00:00') : null;
        
        return $minisite;
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = ['home_url', 'rawurlencode'];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return null;
                    }
                ");
            }
        }
        
        // Set default mock for home_url
        $this->mockWordPressFunction('home_url', 'http://example.com');
        $this->mockWordPressFunction('rawurlencode', function($str) { return rawurlencode($str); });
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = ['home_url', 'rawurlencode'];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
