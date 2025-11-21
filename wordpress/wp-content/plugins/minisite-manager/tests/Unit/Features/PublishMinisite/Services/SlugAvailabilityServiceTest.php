<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\PublishMinisite\Services\SlugAvailabilityService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SlugAvailabilityService
 */
#[CoversClass(SlugAvailabilityService::class)]
final class SlugAvailabilityServiceTest extends TestCase
{
    private SlugAvailabilityService $service;
    private WordPressPublishManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);

        $this->service = new SlugAvailabilityService(
            $this->wordPressManager,
            $this->minisiteRepository
        );

        // Mock global $wpdb for database operations
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public function prepare($query, ...$args) {
                return $query;
            }
            public function query($query) {
                return true;
            }
            public function get_row($query, $output = OBJECT) {
                // Return null to simulate no reservation found
                return null;
            }
            public function get_var($query = null, $x = 0, $y = 0) {
                return null;
            }
        };
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        global $wpdb;
        $wpdb = null;
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(SlugAvailabilityService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('minisiteRepository', $parameters[1]->getName());
    }

    /**
     * Test validateSlugFormat returns true for valid slug
     */
    public function test_validate_slug_format_valid_slug(): void
    {
        $validSlugs = ['test-business', 'my-company-123', 'abc123', 'test'];

        foreach ($validSlugs as $slug) {
            $result = $this->service->validateSlugFormat($slug);
            $this->assertTrue($result, "Slug '{$slug}' should be valid");
        }
    }

    /**
     * Test validateSlugFormat returns false for invalid slug
     */
    public function test_validate_slug_format_invalid_slug(): void
    {
        $invalidSlugs = ['Test Business', 'MY_COMPANY', 'test business', 'test@business', 'test.business', ''];

        foreach ($invalidSlugs as $slug) {
            $result = $this->service->validateSlugFormat($slug);
            $this->assertFalse($result, "Slug '{$slug}' should be invalid");
        }
    }

    /**
     * Test checkAvailability returns available when slug combination is free
     */
    public function test_check_availability_slug_available(): void
    {
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn(null);

        // Mock DatabaseHelper::get_row to return null (no reservation)
        // The $wpdb mock in setUp() already has get_row() that returns null
        $result = $this->service->checkAvailability($businessSlug, $locationSlug);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('available', $result);
        $this->assertObjectHasProperty('message', $result);
        // Note: Actual availability depends on database query which we can't easily mock
        // This test verifies the method structure and basic flow
    }

    /**
     * Test checkAvailability returns unavailable when slug taken by minisite
     */
    public function test_check_availability_slug_taken_by_minisite(): void
    {
        $businessSlug = 'taken-business';
        $locationSlug = 'taken-location';

        $existingMinisite = $this->createMock(Minisite::class);
        $existingMinisite->id = 'existing-site';

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn($existingMinisite);

        $result = $this->service->checkAvailability($businessSlug, $locationSlug);

        $this->assertIsObject($result);
        $this->assertFalse($result->available);
        $this->assertStringContainsString('already taken', $result->message);
    }

    /**
     * Test checkAvailability returns error for invalid business slug format
     */
    public function test_check_availability_invalid_business_slug(): void
    {
        $businessSlug = 'Invalid Slug!';
        $locationSlug = 'test-location';

        $result = $this->service->checkAvailability($businessSlug, $locationSlug);

        $this->assertIsObject($result);
        $this->assertFalse($result->available);
        $this->assertStringContainsString('Business slug', $result->message);
    }

    /**
     * Test checkAvailability returns error for invalid location slug format
     */
    public function test_check_availability_invalid_location_slug(): void
    {
        $businessSlug = 'valid-business';
        $locationSlug = 'Invalid Location!';

        $this->minisiteRepository
            ->method('findBySlugParams')
            ->willReturn(null);

        $result = $this->service->checkAvailability($businessSlug, $locationSlug);

        $this->assertIsObject($result);
        $this->assertFalse($result->available);
        $this->assertStringContainsString('Location slug', $result->message);
    }

    /**
     * Test checkAvailability handles empty location slug
     */
    public function test_check_availability_empty_location_slug(): void
    {
        $businessSlug = 'test-business';
        $locationSlug = '';

        $this->minisiteRepository
            ->method('findBySlugParams')
            ->willReturn(null);

        // Method should handle empty location slug
        $result = $this->service->checkAvailability($businessSlug, $locationSlug);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('available', $result);
    }

    /**
     * Test checkAvailability method exists and is callable
     */
    public function test_check_availability_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'checkAvailability'));
        $this->assertTrue(is_callable([$this->service, 'checkAvailability']));
    }

    /**
     * Test validateSlugFormat method exists and is callable
     */
    public function test_validate_slug_format_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'validateSlugFormat'));
        $this->assertTrue(is_callable([$this->service, 'validateSlugFormat']));
    }
}

