<?php

namespace Tests\Unit\Features\MinisiteDisplay\Handlers;

use Minisite\Features\MinisiteViewer\Commands\DisplayMinisiteCommand;
use Minisite\Features\MinisiteViewer\Handlers\DisplayHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteDisplayService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test DisplayHandler
 * 
 * Tests the DisplayHandler to ensure proper delegation to MinisiteDisplayService
 */
final class DisplayHandlerTest extends TestCase
{
    private MinisiteDisplayService|MockObject $displayService;
    private DisplayHandler $displayHandler;

    protected function setUp(): void
    {
        $this->displayService = $this->createMock(MinisiteDisplayService::class);
        $this->displayHandler = new DisplayHandler($this->displayService);
    }

    /**
     * Test handle method delegates to MinisiteDisplayService
     */
    public function test_handle_delegates_to_display_service(): void
    {
        $command = new DisplayMinisiteCommand('coffee-shop', 'downtown');
        $expectedResult = [
            'success' => true,
            'minisite' => (object)[
                'id' => '123',
                'name' => 'Coffee Shop',
                'business_slug' => 'coffee-shop',
                'location_slug' => 'downtown'
            ]
        ];

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with minisite not found
     */
    public function test_handle_with_minisite_not_found(): void
    {
        $command = new DisplayMinisiteCommand('nonexistent', 'location');
        $expectedResult = [
            'success' => false,
            'error' => 'Minisite not found'
        ];

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with database error
     */
    public function test_handle_with_database_error(): void
    {
        $command = new DisplayMinisiteCommand('business', 'location');
        $expectedResult = [
            'success' => false,
            'error' => 'Error retrieving minisite: Database connection failed'
        ];

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Error retrieving minisite', $result['error']);
    }

    /**
     * Test handle method with empty slugs
     */
    public function test_handle_with_empty_slugs(): void
    {
        $command = new DisplayMinisiteCommand('', '');
        $expectedResult = [
            'success' => false,
            'error' => 'Minisite not found'
        ];

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with special characters in slugs
     */
    public function test_handle_with_special_characters(): void
    {
        $command = new DisplayMinisiteCommand('café-&-restaurant', 'main-street-123');
        $expectedResult = [
            'success' => true,
            'minisite' => (object)[
                'id' => '456',
                'name' => 'Café & Restaurant',
                'business_slug' => 'café-&-restaurant',
                'location_slug' => 'main-street-123'
            ]
        ];

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method returns exactly what MinisiteDisplayService returns
     */
    public function test_handle_returns_service_result_unchanged(): void
    {
        $command = new DisplayMinisiteCommand('business', 'location');
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

        $this->displayService
            ->expects($this->once())
            ->method('getMinisiteForDisplay')
            ->willReturn($expectedResult);

        $result = $this->displayHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('additional_data', $result['minisite']);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->displayHandler);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals(MinisiteDisplayService::class, $params[0]->getType()->getName());
    }
}
