<?php

namespace Tests\Unit\Features\MinisiteListing\Handlers;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test ListMinisitesHandler
 * 
 * Tests the ListMinisitesHandler to ensure proper delegation to MinisiteListingService
 */
final class ListMinisitesHandlerTest extends TestCase
{
    private MinisiteListingService|MockObject $listingService;
    private ListMinisitesHandler $listMinisitesHandler;

    protected function setUp(): void
    {
        $this->listingService = $this->createMock(MinisiteListingService::class);
        $this->listMinisitesHandler = new ListMinisitesHandler($this->listingService);
    }

    /**
     * Test handle method delegates to MinisiteListingService
     */
    public function test_handle_delegates_to_listing_service(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $expectedResult = [
            'success' => true,
            'minisites' => [
                [
                    'id' => '1',
                    'title' => 'Test Minisite',
                    'name' => 'test-minisite',
                    'status' => 'published',
                    'route' => '/b/test/business'
                ]
            ]
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with failed listing
     */
    public function test_handle_with_failed_listing(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $expectedResult = [
            'success' => false,
            'error' => 'Failed to retrieve minisites: Database connection error'
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with empty results
     */
    public function test_handle_with_empty_results(): void
    {
        $command = new ListMinisitesCommand(456, 50, 0);
        $expectedResult = [
            'success' => true,
            'minisites' => []
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['minisites']);
    }

    /**
     * Test handle method with pagination
     */
    public function test_handle_with_pagination(): void
    {
        $command = new ListMinisitesCommand(123, 10, 20);
        $expectedResult = [
            'success' => true,
            'minisites' => [
                [
                    'id' => '21',
                    'title' => 'Paged Minisite',
                    'name' => 'paged-minisite',
                    'status' => 'draft'
                ]
            ]
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with different user IDs
     */
    public function test_handle_with_different_user_ids(): void
    {
        $command = new ListMinisitesCommand(999, 25, 0);
        $expectedResult = [
            'success' => true,
            'minisites' => [
                [
                    'id' => '5',
                    'title' => 'User 999 Minisite',
                    'name' => 'user-999-minisite',
                    'status' => 'published'
                ]
            ]
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method returns exactly what service returns
     */
    public function test_handle_returns_service_result_unchanged(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $expectedResult = [
            'success' => true,
            'minisites' => [
                [
                    'id' => '1',
                    'title' => 'Test Minisite',
                    'name' => 'test-minisite',
                    'status' => 'published',
                    'route' => '/b/test/business',
                    'additional_field' => 'some_value'
                ]
            ],
            'total_count' => 1,
            'has_more' => false
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    /**
     * Test handle method with zero limit and offset
     */
    public function test_handle_with_zero_limit_and_offset(): void
    {
        $command = new ListMinisitesCommand(123, 0, 0);
        $expectedResult = [
            'success' => true,
            'minisites' => []
        ];

        $this->listingService
            ->expects($this->once())
            ->method('listMinisites')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->listMinisitesHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }
}
