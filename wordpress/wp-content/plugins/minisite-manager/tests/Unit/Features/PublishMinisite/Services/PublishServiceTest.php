<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\Services\SlugAvailabilityService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PublishService
 */
#[CoversClass(PublishService::class)]
final class PublishServiceTest extends TestCase
{
    private PublishService $service;
    private WordPressPublishManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;
    private SlugAvailabilityService|MockObject $slugAvailabilityService;
    private ReservationService|MockObject $reservationService;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->slugAvailabilityService = $this->createMock(SlugAvailabilityService::class);
        $this->reservationService = $this->createMock(ReservationService::class);

        $this->service = new PublishService(
            $this->wordPressManager,
            $this->minisiteRepository,
            $this->slugAvailabilityService,
            $this->reservationService
        );
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(PublishService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('minisiteRepository', $parameters[1]->getName());
        $this->assertEquals('slugAvailabilityService', $parameters[2]->getName());
        $this->assertEquals('reservationService', $parameters[3]->getName());
    }

    /**
     * Test getSlugAvailabilityService returns service
     */
    public function test_get_slug_availability_service_returns_service(): void
    {
        $result = $this->service->getSlugAvailabilityService();

        $this->assertInstanceOf(SlugAvailabilityService::class, $result);
        $this->assertSame($this->slugAvailabilityService, $result);
    }

    /**
     * Test getMinisiteForPublishing returns minisite data when found
     */
    public function test_get_minisite_for_publishing_returns_data_when_found(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;

        $mockMinisite = $this->createMock(Minisite::class);
        $mockMinisite->id = $siteId;
        $mockMinisite->createdBy = $userId;
        $mockMinisite->slugs = new SlugPair(
            business: 'test-business',
            location: 'test-location'
        );

        $mockUser = new \WP_User($userId, 'testuser');
        $mockUser->ID = $userId;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $result = $this->service->getMinisiteForPublishing($siteId);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('minisite', $result);
        $this->assertObjectHasProperty('currentSlugs', $result);
        $this->assertEquals($mockMinisite, $result->minisite);
        $this->assertEquals('test-business', $result->currentSlugs['business']);
        $this->assertEquals('test-location', $result->currentSlugs['location']);
    }

    /**
     * Test getMinisiteForPublishing throws exception when minisite not found
     */
    public function test_get_minisite_for_publishing_throws_exception_when_not_found(): void
    {
        $siteId = 'non-existent';

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->service->getMinisiteForPublishing($siteId);
    }

    /**
     * Test getMinisiteForPublishing throws exception when access denied
     */
    public function test_get_minisite_for_publishing_throws_exception_when_access_denied(): void
    {
        $siteId = 'test-site-123';
        $ownerId = 456;
        $currentUserId = 789; // Different user

        $mockMinisite = $this->createMock(Minisite::class);
        $mockMinisite->id = $siteId;
        $mockMinisite->createdBy = $ownerId;

        $mockUser = new \WP_User($currentUserId, 'testuser');
        $mockUser->ID = $currentUserId;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->service->getMinisiteForPublishing($siteId);
    }

    /**
     * Test getMinisiteForPublishing handles empty slugs
     */
    public function test_get_minisite_for_publishing_handles_empty_slugs(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;

        $mockMinisite = $this->createMock(Minisite::class);
        $mockMinisite->id = $siteId;
        $mockMinisite->createdBy = $userId;
        $mockMinisite->slugs = null; // No slugs

        $mockUser = new \WP_User($userId, 'testuser');
        $mockUser->ID = $userId;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $result = $this->service->getMinisiteForPublishing($siteId);

        $this->assertIsObject($result);
        $this->assertEquals('', $result->currentSlugs['business']);
        $this->assertEquals('', $result->currentSlugs['location']);
    }

    /**
     * Test getMinisiteForPublishing returns correct structure
     */
    public function test_get_minisite_for_publishing_returns_correct_structure(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;

        $mockMinisite = $this->createMock(Minisite::class);
        $mockMinisite->id = $siteId;
        $mockMinisite->createdBy = $userId;
        $mockMinisite->slugs = new SlugPair(
            business: 'my-business',
            location: 'my-location'
        );

        $mockUser = new \WP_User($userId, 'testuser');
        $mockUser->ID = $userId;

        $this->minisiteRepository
            ->method('findById')
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $result = $this->service->getMinisiteForPublishing($siteId);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('minisite', $result);
        $this->assertObjectHasProperty('currentSlugs', $result);
        $this->assertIsArray($result->currentSlugs);
        $this->assertArrayHasKey('business', $result->currentSlugs);
        $this->assertArrayHasKey('location', $result->currentSlugs);
    }
}

