<?php

namespace Tests\Unit\Features\MinisiteViewer\Hooks;

use Minisite\Features\MinisiteViewer\Hooks\ViewHooksFactory;
use Minisite\Features\MinisiteViewer\Hooks\ViewHooks;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\Http\ViewRequestHandler;
use Minisite\Features\MinisiteViewer\Http\ViewResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\ViewRenderer;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

/**
 * Test ViewHooksFactory
 *
 * Tests the ViewHooksFactory for proper dependency injection and object creation
 */
final class ViewHooksFactoryTest extends TestCase
{
    private ViewHooksFactory $viewHooksFactory;

    protected function setUp(): void
    {
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(FakeWpdb::class);
        $wpdb->prefix = 'wp_';

        // Mock $GLOBALS for repositories (required by factory)
        $GLOBALS['minisite_version_repository'] = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface::class);

        $this->viewHooksFactory = new ViewHooksFactory();
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['minisite_version_repository']);
        global $wpdb;
        $wpdb = null;

        parent::tearDown();
    }

    /**
     * Test create returns ViewHooks instance
     */
    public function test_create_returns_display_hooks_instance(): void
    {
        $result = ViewHooksFactory::create();

        $this->assertInstanceOf(ViewHooks::class, $result);
    }

    /**
     * Test create returns new instance each time
     */
    public function test_create_returns_new_instance_each_time(): void
    {
        $result1 = ViewHooksFactory::create();
        $result2 = ViewHooksFactory::create();

        $this->assertNotSame($result1, $result2);
    }

    /**
     * Test create creates properly configured ViewHooks
     */
    public function test_create_creates_properly_configured_display_hooks(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Verify the ViewHooks has a MinisitePageController
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        $this->assertInstanceOf(MinisitePageController::class, $controller);
    }

    /**
     * Test create creates MinisitePageController with all dependencies
     */
    public function test_create_creates_minisite_page_controller_with_all_dependencies(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Verify controller has all required dependencies
        $controllerReflection = new \ReflectionClass($controller);
        $constructor = $controllerReflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(6, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $expectedTypes = [
            ViewHandler::class,
            MinisiteViewService::class,
            ViewRequestHandler::class,
            ViewResponseHandler::class,
            ViewRenderer::class,
            \Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager::class
        ];

        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }

    /**
     * Test createViewHooks creates ViewHandler with MinisiteViewService
     */
    public function test_create_display_hooks_creates_display_handler_with_display_service(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Get the ViewHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $handlerProperty = $controllerReflection->getProperty('viewHandler');
        $handlerProperty->setAccessible(true);
        $handler = $handlerProperty->getValue($controller);

        $this->assertInstanceOf(ViewHandler::class, $handler);
    }

    /**
     * Test createViewHooks creates MinisiteViewService with WordPressMinisiteManager
     */
    public function test_create_display_hooks_creates_display_service_with_wordpress_manager(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Get the MinisiteViewService from controller
        $controllerReflection = new \ReflectionClass($controller);
        $serviceProperty = $controllerReflection->getProperty('viewService');
        $serviceProperty->setAccessible(true);
        $service = $serviceProperty->getValue($controller);

        $this->assertInstanceOf(MinisiteViewService::class, $service);
    }

    /**
     * Test createViewHooks creates ViewRenderer with TimberRenderer
     */
    public function test_create_display_hooks_creates_display_renderer_with_timber_renderer(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Get the ViewRenderer from controller
        $controllerReflection = new \ReflectionClass($controller);
        $rendererProperty = $controllerReflection->getProperty('renderer');
        $rendererProperty->setAccessible(true);
        $renderer = $rendererProperty->getValue($controller);

        $this->assertInstanceOf(ViewRenderer::class, $renderer);
    }

    /**
     * Test createViewHooks creates ViewRequestHandler
     */
    public function test_create_display_hooks_creates_display_request_handler(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Get the ViewRequestHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $requestHandlerProperty = $controllerReflection->getProperty('requestHandler');
        $requestHandlerProperty->setAccessible(true);
        $requestHandler = $requestHandlerProperty->getValue($controller);

        $this->assertInstanceOf(ViewRequestHandler::class, $requestHandler);
    }

    /**
     * Test createViewHooks creates ViewResponseHandler
     */
    public function test_create_display_hooks_creates_display_response_handler(): void
    {
        $viewHooks = ViewHooksFactory::create();

        // Get the controller from ViewHooks
        $reflection = new \ReflectionClass($viewHooks);
        $controllerProperty = $reflection->getProperty('minisitePageController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($viewHooks);

        // Get the ViewResponseHandler from controller
        $controllerReflection = new \ReflectionClass($controller);
        $responseHandlerProperty = $controllerReflection->getProperty('responseHandler');
        $responseHandlerProperty->setAccessible(true);
        $responseHandler = $responseHandlerProperty->getValue($controller);

        $this->assertInstanceOf(ViewResponseHandler::class, $responseHandler);
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass($this->viewHooksFactory);
        $constructor = $reflection->getConstructor();

        // ViewHooksFactory uses PHP's default constructor (no explicit constructor)
        $this->assertNull($constructor);
    }

    /**
     * Test create method is public
     */
    public function test_create_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->viewHooksFactory);
        $method = $reflection->getMethod('create');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test create method is static
     */
    public function test_create_method_is_static(): void
    {
        $reflection = new \ReflectionClass($this->viewHooksFactory);
        $method = $reflection->getMethod('create');

        $this->assertTrue($method->isStatic());
    }
}
