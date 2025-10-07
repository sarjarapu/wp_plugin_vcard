<?php

namespace Tests\Unit\Features\MinisiteViewer\Handlers;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test ViewHandler
 * 
 * Tests the ViewHandler to ensure proper delegation to MinisiteViewService
 */
final class ViewHandlerTest extends TestCase
{
    private MinisiteViewService|MockObject $viewService;
    private ViewHandler $viewHandler;

    protected function setUp(): void
    {
        $this->viewService = $this->createMock(MinisiteViewService::class);
        $this->viewHandler = new ViewHandler($this->viewService);
    }

    /**
     * Test handle method delegates to MinisiteViewService
     */
    public function test_handle_delegates_to_display_service(): void
    {
        $command = new ViewMinisiteCommand('coffee-shop', 'downtown');
        $expectedResult = [
            'success' => true,
            'minisite' => (object)[
                'id' => '123',
                'name' => 'Coffee Shop',
                'business_slug' => 'coffee-shop',
                'location_slug' => 'downtown'
            ]
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with minisite not found
     */
    public function test_handle_with_minisite_not_found(): void
    {
        $command = new ViewMinisiteCommand('nonexistent', 'location');
        $expectedResult = [
            'success' => false,
            'error' => 'Minisite not found'
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with database error
     */
    public function test_handle_with_database_error(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');
        $expectedResult = [
            'success' => false,
            'error' => 'Error retrieving minisite: Database connection failed'
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Error retrieving minisite', $result['error']);
    }

    /**
     * Test handle method with empty slugs
     */
    public function test_handle_with_empty_slugs(): void
    {
        $command = new ViewMinisiteCommand('', '');
        $expectedResult = [
            'success' => false,
            'error' => 'Minisite not found'
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with special characters in slugs
     */
    public function test_handle_with_special_characters(): void
    {
        $command = new ViewMinisiteCommand('café-&-restaurant', 'main-street-123');
        $expectedResult = [
            'success' => true,
            'minisite' => (object)[
                'id' => '456',
                'name' => 'Café & Restaurant',
                'business_slug' => 'café-&-restaurant',
                'location_slug' => 'main-street-123'
            ]
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method returns exactly what MinisiteViewService returns
     */
    public function test_handle_returns_service_result_unchanged(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');
        $expectedResult = [
            'success' => true,
            'minisite' => [
                'id' => '789',
                'name' => 'Business Name',
                'business_slug' => 'business',
                'location_slug' => 'location',
                'additional_data' => 'some_value'
            ]
        ];

        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForView')
            ->willReturn($expectedResult);

        $result = $this->viewHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('additional_data', $result['minisite']);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->viewHandler);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals(MinisiteViewService::class, $params[0]->getType()->getName());
    }
}
