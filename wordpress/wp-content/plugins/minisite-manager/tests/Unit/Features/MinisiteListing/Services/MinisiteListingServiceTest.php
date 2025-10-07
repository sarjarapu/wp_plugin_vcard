<?php

namespace Tests\Unit\Features\MinisiteListing\Services;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteListingService
 * 
 * Tests the MinisiteListingService with mocked WordPress functions
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class MinisiteListingServiceTest extends TestCase
{
    private MinisiteListingService $listingService;
    private WordPressListingManager|MockObject $listingManager;

    protected function setUp(): void
    {
        $this->listingManager = $this->createMock(WordPressListingManager::class);
        $this->listingService = new MinisiteListingService($this->listingManager);
    }

    /**
     * Test listMinisites with successful result
     */
    public function test_list_minisites_with_successful_result(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $mockMinisites = [
            [
                'id' => '1',
                'title' => 'Test Minisite 1',
                'name' => 'test-minisite-1',
                'slugs' => ['business' => 'test', 'location' => 'business'],
                'route' => '/b/test/business',
                'location' => 'New York, NY, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 10:00',
                'published_at' => '2025-01-06 09:00',
                'subscription' => 'Pro',
                'online' => 'Yes'
            ],
            [
                'id' => '2',
                'title' => 'Test Minisite 2',
                'name' => 'test-minisite-2',
                'slugs' => ['business' => 'test2', 'location' => 'business2'],
                'route' => '/b/test2/business2',
                'location' => 'Los Angeles, CA, US',
                'status' => 'draft',
                'status_chip' => 'Draft',
                'updated_at' => '2025-01-06 11:00',
                'published_at' => null,
                'subscription' => 'Basic',
                'online' => 'No'
            ]
        ];

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
            ->with(123, 50, 0)
            ->willReturn($mockMinisites);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisites, $result['minisites']);
        $this->assertCount(2, $result['minisites']);
    }

    /**
     * Test listMinisites with empty results
     */
    public function test_list_minisites_with_empty_results(): void
    {
        $command = new ListMinisitesCommand(456, 50, 0);

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
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
        $mockMinisites = [
            [
                'id' => '21',
                'title' => 'Paged Minisite',
                'name' => 'paged-minisite',
                'slugs' => ['business' => 'paged', 'location' => 'business'],
                'route' => '/b/paged/business',
                'location' => 'Chicago, IL, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 12:00',
                'published_at' => '2025-01-06 11:30',
                'subscription' => 'Pro',
                'online' => 'Yes'
            ]
        ];

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
            ->with(123, 10, 20)
            ->willReturn($mockMinisites);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisites, $result['minisites']);
        $this->assertCount(1, $result['minisites']);
    }

    /**
     * Test listMinisites with database exception
     */
    public function test_list_minisites_with_database_exception(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
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

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
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
        $mockMinisites = [
            [
                'id' => '5',
                'title' => 'User 999 Minisite',
                'name' => 'user-999-minisite',
                'slugs' => ['business' => 'user999', 'location' => 'business'],
                'route' => '/b/user999/business',
                'location' => 'Miami, FL, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 13:00',
                'published_at' => '2025-01-06 12:30',
                'subscription' => 'Enterprise',
                'online' => 'Yes'
            ]
        ];

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
            ->with(999, 50, 0)
            ->willReturn($mockMinisites);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisites, $result['minisites']);
    }

    /**
     * Test listMinisites with zero limit
     */
    public function test_list_minisites_with_zero_limit(): void
    {
        $command = new ListMinisitesCommand(123, 0, 0);

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
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

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
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
        $mockMinisites = [
            [
                'id' => '1',
                'title' => 'Published Minisite',
                'name' => 'published-minisite',
                'slugs' => ['business' => 'published', 'location' => 'business'],
                'route' => '/b/published/business',
                'location' => 'Seattle, WA, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 14:00',
                'published_at' => '2025-01-06 13:30',
                'subscription' => 'Pro',
                'online' => 'Yes'
            ],
            [
                'id' => '2',
                'title' => 'Draft Minisite',
                'name' => 'draft-minisite',
                'slugs' => ['business' => 'draft', 'location' => 'business'],
                'route' => '/b/draft/business',
                'location' => 'Portland, OR, US',
                'status' => 'draft',
                'status_chip' => 'Draft',
                'updated_at' => '2025-01-06 15:00',
                'published_at' => null,
                'subscription' => 'Basic',
                'online' => 'No'
            ]
        ];

        $this->listingManager
            ->expects($this->once())
            ->method('listMinisitesByOwner')
            ->with(123, 50, 0)
            ->willReturn($mockMinisites);

        $result = $this->listingService->listMinisites($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisites, $result['minisites']);
        $this->assertCount(2, $result['minisites']);
        
        // Verify status chips are correctly set
        $this->assertEquals('Published', $result['minisites'][0]['status_chip']);
        $this->assertEquals('Draft', $result['minisites'][1]['status_chip']);
    }
}
