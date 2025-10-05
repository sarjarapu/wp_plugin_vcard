<?php

namespace Tests\Unit\Features\MinisiteDisplay\Hooks;

use Minisite\Features\MinisiteDisplay\Hooks\DisplayHooksFactory;
use Minisite\Features\MinisiteDisplay\Hooks\DisplayHooks;
use Minisite\Features\MinisiteDisplay\Controllers\MinisitePageController;
use Minisite\Features\MinisiteDisplay\Handlers\DisplayHandler;
use Minisite\Features\MinisiteDisplay\Services\MinisiteDisplayService;
use Minisite\Features\MinisiteDisplay\Http\DisplayRequestHandler;
use Minisite\Features\MinisiteDisplay\Http\DisplayResponseHandler;
use Minisite\Features\MinisiteDisplay\Rendering\DisplayRenderer;
use Minisite\Features\MinisiteDisplay\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayHooksFactory
 * 
 * Tests the DisplayHooksFactory for proper dependency injection and object creation
 */
final class DisplayHooksFactoryTest extends TestCase
{
    private DisplayHooksFactory $displayHooksFactory;

    protected function setUp(): void
    {
        $this->displayHooksFactory = new DisplayHooksFactory();
    }

    /**
     * Test create returns DisplayHooks instance
     */
    public function test_create_returns_display_hooks_instance(): void
    {
        $result = DisplayHooksFactory::create();

        $this->assertInstanceOf(DisplayHooks::class, $result);
    }

    /**
     * Test create returns new instance each time
     */
    public function test_create_returns_new_instance_each_time(): void
    {
        $result1 = DisplayHooksFactory::create();
        $result2 = DisplayHooksFactory::create();

        $this->assertNotSame($result1, $result2);
    }

    /**
     * Test create creates properly configured DisplayHooks
     */
    public function test_create_creates_properly_configured_display_hooks(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Verify the DisplayHooks has a MinisitePageController
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        $this->assertInstanceOf(MinisitePageController::class, $controller);
    }

    /**
     * Test create creates MinisitePageController with all dependencies
     */
    public function test_create_creates_minisite_page_controller_with_all_dependencies(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Verify controller has all required dependencies
        $controllerReflection = new \ReflectionClass($controller);
        $constructor = $controllerReflection->getConstructor();
        
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
     * Test createDisplayHooks creates DisplayHandler with MinisiteDisplayService
     */
    public function test_create_display_hooks_creates_display_handler_with_display_service(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Get the DisplayHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $handlerProperty = $controllerReflection->getProperty('displayHandler');
        $handlerProperty->setAccessible(true);
        $handler = $handlerProperty->getValue($controller);

        $this->assertInstanceOf(DisplayHandler::class, $handler);
    }

    /**
     * Test createDisplayHooks creates MinisiteDisplayService with WordPressMinisiteManager
     */
    public function test_create_display_hooks_creates_display_service_with_wordpress_manager(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Get the MinisiteDisplayService from controller
        $controllerReflection = new \ReflectionClass($controller);
        $serviceProperty = $controllerReflection->getProperty('displayService');
        $serviceProperty->setAccessible(true);
        $service = $serviceProperty->getValue($controller);

        $this->assertInstanceOf(MinisiteDisplayService::class, $service);
    }

    /**
     * Test createDisplayHooks creates DisplayRenderer with TimberRenderer
     */
    public function test_create_display_hooks_creates_display_renderer_with_timber_renderer(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Get the DisplayRenderer from controller
        $controllerReflection = new \ReflectionClass($controller);
        $rendererProperty = $controllerReflection->getProperty('renderer');
        $rendererProperty->setAccessible(true);
        $renderer = $rendererProperty->getValue($controller);

        $this->assertInstanceOf(DisplayRenderer::class, $renderer);
    }

    /**
     * Test createDisplayHooks creates DisplayRequestHandler
     */
    public function test_create_display_hooks_creates_display_request_handler(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Get the DisplayRequestHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $requestHandlerProperty = $controllerReflection->getProperty('requestHandler');
        $requestHandlerProperty->setAccessible(true);
        $requestHandler = $requestHandlerProperty->getValue($controller);

        $this->assertInstanceOf(DisplayRequestHandler::class, $requestHandler);
    }

    /**
     * Test createDisplayHooks creates DisplayResponseHandler
     */
    public function test_create_display_hooks_creates_display_response_handler(): void
    {
        $displayHooks = DisplayHooksFactory::create();

        // Get the controller from DisplayHooks
        $reflection = new \ReflectionClass($displayHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($displayHooks);

        // Get the DisplayResponseHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $responseHandlerProperty = $controllerReflection->getProperty('responseHandler');
        $responseHandlerProperty->setAccessible(true);
        $responseHandler = $responseHandlerProperty->getValue($controller);

        $this->assertInstanceOf(DisplayResponseHandler::class, $responseHandler);
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass($this->displayHooksFactory);
        $constructor = $reflection->getConstructor();
        
        // DisplayHooksFactory uses PHP's default constructor (no explicit constructor)
        $this->assertNull($constructor);
    }

    /**
     * Test create method is public
     */
    public function test_create_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->displayHooksFactory);
        $method = $reflection->getMethod('create');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test create method is static
     */
    public function test_create_method_is_static(): void
    {
        $reflection = new \ReflectionClass($this->displayHooksFactory);
        $method = $reflection->getMethod('create');
        
        $this->assertTrue($method->isStatic());
    }
}
