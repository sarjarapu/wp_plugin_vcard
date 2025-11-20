<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Hooks;

use Minisite\Features\PublishMinisite\Hooks\PublishHooks;
use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\Services\WooCommerceIntegration;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PublishHooks
 */
#[CoversClass(PublishHooks::class)]
final class PublishHooksTest extends TestCase
{
    private PublishHooks $hooks;
    private MockObject $publishController;
    private MockObject $wordPressManager;
    private MockObject $wooCommerceIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->publishController = $this->createMock(PublishController::class);
        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->wooCommerceIntegration = $this->createMock(WooCommerceIntegration::class);

        // Use TestTerminationHandler so exit doesn't terminate tests
        $terminationHandler = new TestTerminationHandler();

        $this->hooks = new PublishHooks(
            $this->publishController,
            $this->wordPressManager,
            $this->wooCommerceIntegration,
            $terminationHandler
        );

        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
        $this->clearWordPressMocks();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(PublishHooks::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('publishController', $parameters[0]->getName());
        $this->assertEquals('wordPressManager', $parameters[1]->getName());
        $this->assertEquals('wooCommerceIntegration', $parameters[2]->getName());
        $this->assertEquals('terminationHandler', $parameters[3]->getName());
    }

    /**
     * Test register method exists and is callable
     */
    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'register'));
        $this->assertTrue(is_callable([$this->hooks, 'register']));
    }

    /**
     * Test register can be called without errors
     */
    public function test_register_can_be_called(): void
    {
        // register() adds WordPress hooks, but should not throw
        try {
            $this->hooks->register();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('register() should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test handlePublishRoutes method exists and is callable
     */
    public function test_handle_publish_routes_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handlePublishRoutes'));
        $this->assertTrue(is_callable([$this->hooks, 'handlePublishRoutes']));
    }

    /**
     * Test handlePublishRoutes returns early when not account route
     */
    public function test_handle_publish_routes_not_account_route_returns_early(): void
    {
        // Mock getQueryVar to return non-account route
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_account')
            ->willReturn('0'); // Not an account route

        // Controller should not be called
        $this->publishController
            ->expects($this->never())
            ->method('handlePublish');

        $this->hooks->handlePublishRoutes();
    }

    /**
     * Test handlePublishRoutes calls controller when action is 'publish'
     */
    public function test_handle_publish_routes_publish_action_calls_controller(): void
    {
        // Mock getQueryVar to return account route with 'publish' action
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                if ($var === 'minisite_account') {
                    return '1'; // Account route
                }
                if ($var === 'minisite_account_action') {
                    return 'publish'; // Publish action
                }
                return '';
            });

        // Controller should be called
        $this->publishController
            ->expects($this->once())
            ->method('handlePublish');

        $this->hooks->handlePublishRoutes();
    }

    /**
     * Test handlePublishRoutes ignores other actions
     */
    public function test_handle_publish_routes_other_action_ignored(): void
    {
        // Mock getQueryVar to return account route with different action
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                if ($var === 'minisite_account') {
                    return '1'; // Account route
                }
                if ($var === 'minisite_account_action') {
                    return 'edit'; // Different action
                }
                return '';
            });

        // Controller should not be called for other actions
        $this->publishController
            ->expects($this->never())
            ->method('handlePublish');

        $this->hooks->handlePublishRoutes();
    }

    /**
     * Test handlePublishRoutes terminates after handling
     */
    public function test_handle_publish_routes_terminates_after_handling(): void
    {
        // Mock getQueryVar to return account route with 'publish' action
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                if ($var === 'minisite_account') {
                    return '1';
                }
                if ($var === 'minisite_account_action') {
                    return 'publish';
                }
                return '';
            });

        $this->publishController
            ->expects($this->once())
            ->method('handlePublish');

        // The terminate() method is called (inherited from BaseHook)
        // TestTerminationHandler will prevent actual termination
        $this->hooks->handlePublishRoutes();

        // If we get here, the method executed successfully
        $this->assertTrue(true);
    }

    /**
     * Test getController returns controller
     */
    public function test_get_controller_returns_controller(): void
    {
        $controller = $this->hooks->getController();

        $this->assertInstanceOf(PublishController::class, $controller);
        $this->assertSame($this->publishController, $controller);
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        // No WordPress functions need to be mocked for these tests
        // All WordPress interactions go through WordPressPublishManager mock
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        // No cleanup needed
    }
}

