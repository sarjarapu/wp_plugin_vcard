<?php

namespace Tests\Unit\Features\MinisiteDisplay\Controllers;

use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\Handlers\DisplayHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteDisplayService;
use Minisite\Features\MinisiteViewer\Http\DisplayRequestHandler;
use Minisite\Features\MinisiteViewer\Http\DisplayResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\DisplayRenderer;
use Minisite\Features\MinisiteViewer\Commands\DisplayMinisiteCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test MinisitePageController
 * 
 * Tests the MinisitePageController for proper coordination of minisite display flow
 */
final class MinisitePageControllerTest extends TestCase
{
    private MinisitePageController $minisitePageController;
    private $displayHandler;
    private $displayService;
    private $requestHandler;
    private $responseHandler;
    private $renderer;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->displayHandler = $this->createMock(DisplayHandler::class);
        $this->displayService = $this->createMock(MinisiteDisplayService::class);
        $this->requestHandler = $this->createMock(DisplayRequestHandler::class);
        $this->responseHandler = $this->createMock(DisplayResponseHandler::class);
        $this->renderer = $this->createMock(DisplayRenderer::class);

        // Create MinisitePageController with mocked dependencies
        $this->minisitePageController = new MinisitePageController(
            $this->displayHandler,
            $this->displayService,
            $this->requestHandler,
            $this->responseHandler,
            $this->renderer
        );
    }

    /**
     * Test MinisitePageController can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MinisitePageController::class, $this->minisitePageController);
    }

    /**
     * Test handleDisplay with successful minisite display
     */
    public function test_handle_display_with_successful_minisite_display(): void
    {
        $command = new DisplayMinisiteCommand('coffee-shop', 'downtown');
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Mock request handler to return a display command
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to return success
        $this->displayHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisite' => $mockMinisite]);

        // Mock renderer to render minisite
        $this->renderer->expects($this->once())
            ->method('renderMinisite')
            ->with($mockMinisite);

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test handleDisplay with minisite not found
     */
    public function test_handle_display_with_minisite_not_found(): void
    {
        $command = new DisplayMinisiteCommand('nonexistent', 'location');

        // Mock request handler to return a display command
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to return failure
        $this->displayHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Minisite not found']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Minisite not found');

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test handleDisplay with invalid request (no command)
     */
    public function test_handle_display_with_invalid_request(): void
    {
        // Mock request handler to return null (invalid request)
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn(null);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Invalid request - missing minisite parameters');

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test handleDisplay with exception
     */
    public function test_handle_display_with_exception(): void
    {
        $command = new DisplayMinisiteCommand('business', 'location');

        // Mock request handler to return a command
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to throw exception
        $this->displayHandler->method('handle')
            ->with($command)
            ->willThrowException(new \Exception('Database error'));

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404 with error
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Error: Database error');

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test handleDisplay with empty slugs
     */
    public function test_handle_display_with_empty_slugs(): void
    {
        $command = new DisplayMinisiteCommand('', '');

        // Mock request handler to return a command with empty slugs
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to return failure
        $this->displayHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Minisite not found']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Minisite not found');

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test handleDisplay with special characters in slugs
     */
    public function test_handle_display_with_special_characters(): void
    {
        $command = new DisplayMinisiteCommand('café-&-restaurant', 'main-street-123');
        $mockMinisite = (object)[
            'id' => '456',
            'name' => 'Café & Restaurant',
            'business_slug' => 'café-&-restaurant',
            'location_slug' => 'main-street-123'
        ];

        // Mock request handler to return a command
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to return success
        $this->displayHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisite' => $mockMinisite]);

        // Mock renderer to render minisite
        $this->renderer->expects($this->once())
            ->method('renderMinisite')
            ->with($mockMinisite);

        // Call the method
        $this->minisitePageController->handleDisplay();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->minisitePageController);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(5, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $expectedTypes = [
            DisplayHandler::class,
            MinisiteDisplayService::class,
            DisplayRequestHandler::class,
            DisplayResponseHandler::class,
            DisplayRenderer::class
        ];
        
        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }

    /**
     * Test handleDisplay with database error
     */
    public function test_handle_display_with_database_error(): void
    {
        $command = new DisplayMinisiteCommand('business', 'location');

        // Mock request handler to return a command
        $this->requestHandler->method('handleDisplayRequest')
            ->willReturn($command);

        // Mock display handler to return database error
        $this->displayHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Error retrieving minisite: Database connection failed']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Error retrieving minisite: Database connection failed');

        // Call the method
        $this->minisitePageController->handleDisplay();
    }
}
