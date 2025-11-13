<?php

namespace Tests\Unit\Features\MinisiteListing\Services;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteListingService
 *
 * NOTE: These are "coverage tests" that verify method existence and basic functionality.
 * They use mocked repositories and WordPress managers but do not test complex business logic flows.
 */
final class MinisiteListingServiceTest extends TestCase
{
    private MinisiteListingService $listingService;
    private WordPressListingManager|MockObject $listingManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;

    protected function setUp(): void
    {
        $this->listingManager = $this->createMock(WordPressListingManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->listingService = new MinisiteListingService($this->listingManager, $this->minisiteRepository);

        // Default URL generation mock
        $this->listingManager
            ->method('getHomeUrl')
            ->willReturnCallback(function ($path) {
                return 'http://example.com' . $path;
            });
    }

    /**
     * Helper to create a mock Minisite entity
     */
    private function createMinisiteEntity(
        string $id,
        string $name,
        string $title,
        string $businessSlug,
        string $locationSlug,
        string $city,
        ?string $region,
        string $countryCode,
        string $status,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $publishedAt = null
    ): Minisite {
        return new Minisite(
            id: $id,
            slug: null,
            slugs: new SlugPair($businessSlug, $locationSlug),
            title: $title,
            name: $name,
            city: $city,
            region: $region,
            countryCode: $countryCode,
            postalCode: null,
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: [],
            searchTerms: null,
            status: $status,
            publishStatus: $status,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: $updatedAt ?? new \DateTimeImmutable('2025-01-06 10:00'),
            publishedAt: $publishedAt,
            createdBy: 123,
            updatedBy: null,
            currentVersionId: null
        );
    }

    /**
     * Test listMinisites with successful result
     */
    public function test_list_minisites_with_successful_result(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        $mockEntities = [
            $this->createMinisiteEntity('1', 'test-minisite-1', 'Test Minisite 1', 'test', 'business', 'New York', 'NY', 'US', 'published'),
            $this->createMinisiteEntity('2', 'test-minisite-2', 'Test Minisite 2', 'test2', 'business2', 'Los Angeles', 'CA', 'US', 'draft'),
        ];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 50, 0)
            ->willReturn($mockEntities);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['minisites']);
        $this->assertEquals('1', $result['minisites'][0]['id']);
        $this->assertEquals('Test Minisite 1', $result['minisites'][0]['title']);
        $this->assertEquals('Published', $result['minisites'][0]['status_chip']);
        $this->assertEquals('Draft', $result['minisites'][1]['status_chip']);
    }

    /**
     * Test listMinisites with empty results
     */
    public function test_list_minisites_with_empty_results(): void
    {
        $command = new ListMinisitesCommand(456, 50, 0);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(456, 50, 0)
            ->willReturn([]);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['minisites']);
        $this->assertCount(0, $result['minisites']);
    }

    /**
     * Test listMinisites with pagination
     */
    public function test_list_minisites_with_pagination(): void
    {
        $command = new ListMinisitesCommand(123, 10, 20);

        $mockEntity = $this->createMinisiteEntity('21', 'paged-minisite', 'Paged Minisite', 'paged', 'business', 'Chicago', 'IL', 'US', 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 10, 20)
            ->willReturn([$mockEntity]);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['minisites']);
        $this->assertEquals('21', $result['minisites'][0]['id']);
    }

    /**
     * Test listMinisites with database exception
     */
    public function test_list_minisites_with_database_exception(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 50, 0)
            ->willThrowException(new \Exception('Database connection failed'));

        $result = $this->listingService->listMinisites($command);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to retrieve minisites: Database connection failed', $result['error']);
        $this->assertArrayNotHasKey('minisites', $result);
    }

    /**
     * Test listMinisites with repository exception
     */
    public function test_list_minisites_with_repository_exception(): void
    {
        $command = new ListMinisitesCommand(789, 25, 5);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(789, 25, 5)
            ->willThrowException(new \Exception('Repository query failed'));

        $result = $this->listingService->listMinisites($command);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to retrieve minisites: Repository query failed', $result['error']);
    }

    /**
     * Test listMinisites with different user IDs
     */
    public function test_list_minisites_with_different_user_ids(): void
    {
        $command = new ListMinisitesCommand(999, 50, 0);

        $mockEntity = $this->createMinisiteEntity('5', 'user-999-minisite', 'User 999 Minisite', 'user999', 'business', 'Miami', 'FL', 'US', 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(999, 50, 0)
            ->willReturn([$mockEntity]);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['minisites']);
    }

    /**
     * Test listMinisites with zero limit
     */
    public function test_list_minisites_with_zero_limit(): void
    {
        $command = new ListMinisitesCommand(123, 0, 0);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 0, 0)
            ->willReturn([]);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['minisites']);
    }

    /**
     * Test listMinisites with large offset
     */
    public function test_list_minisites_with_large_offset(): void
    {
        $command = new ListMinisitesCommand(123, 50, 1000);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 50, 1000)
            ->willReturn([]);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['minisites']);
    }

    /**
     * Test listMinisites with mixed status minisites
     */
    public function test_list_minisites_with_mixed_status(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        $mockEntities = [
            $this->createMinisiteEntity('1', 'published-minisite', 'Published Minisite', 'published', 'business', 'Seattle', 'WA', 'US', 'published'),
            $this->createMinisiteEntity('2', 'draft-minisite', 'Draft Minisite', 'draft', 'business', 'Portland', 'OR', 'US', 'draft'),
        ];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('listByOwner')
            ->with(123, 50, 0)
            ->willReturn($mockEntities);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['minisites']);

        // Verify status chips are correctly set
        $this->assertEquals('Published', $result['minisites'][0]['status_chip']);
        $this->assertEquals('Draft', $result['minisites'][1]['status_chip']);
    }
}
