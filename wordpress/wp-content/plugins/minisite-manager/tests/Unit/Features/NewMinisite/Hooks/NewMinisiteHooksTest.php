<?php

declare(strict_types=1);

namespace Tests\Unit\Features\NewMinisite\Hooks;

use Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks;
use Minisite\Features\NewMinisite\Controllers\NewMinisiteController;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NewMinisiteHooks
 */
#[CoversClass(NewMinisiteHooks::class)]
final class NewMinisiteHooksTest extends TestCase
{
    private NewMinisiteHooks $hooks;
    private MockObject $newMinisiteController;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->newMinisiteController = $this->createMock(NewMinisiteController::class);
        $this->wordPressManager = $this->createMock(WordPressNewMinisiteManager::class);

        // Use TestTerminationHandler so exit doesn't terminate tests
        $terminationHandler = new TestTerminationHandler();

        $this->hooks = new NewMinisiteHooks(
            $this->newMinisiteController,
            $this->wordPressManager,
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
        $reflection = new \ReflectionClass(NewMinisiteHooks::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('newMinisiteController', $parameters[0]->getName());
        $this->assertEquals('wordPressManager', $parameters[1]->getName());
        $this->assertEquals('terminationHandler', $parameters[2]->getName());
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
        // register() is currently a no-op (just a comment), but should not throw
        try {
            $this->hooks->register();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('register() should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test handleNewMinisiteRoutes method exists and is callable
     */
    public function test_handle_new_minisite_routes_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handleNewMinisiteRoutes'));
        $this->assertTrue(is_callable([$this->hooks, 'handleNewMinisiteRoutes']));
    }

    /**
     * Test handleNewMinisiteRoutes returns early when not account route
     */
    public function test_handle_new_minisite_routes_not_account_route_returns_early(): void
    {
        // Mock getQueryVar to return non-account route
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_account')
            ->willReturn('0'); // Not an account route

        // Controller should not be called
        $this->newMinisiteController
            ->expects($this->never())
            ->method('handleNewMinisite');

        $this->hooks->handleNewMinisiteRoutes();
    }

    /**
     * Test handleNewMinisiteRoutes calls controller when action is 'new'
     */
    public function test_handle_new_minisite_routes_new_action_calls_controller(): void
    {
        // Mock getQueryVar to return account route with 'new' action
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                if ($var === 'minisite_account') {
                    return '1'; // Account route
                }
                if ($var === 'minisite_account_action') {
                    return 'new'; // New minisite action
                }
                return '';
            });

        // Controller should be called
        $this->newMinisiteController
            ->expects($this->once())
            ->method('handleNewMinisite');

        $this->hooks->handleNewMinisiteRoutes();
    }

    /**
     * Test handleNewMinisiteRoutes ignores other actions
     */
    public function test_handle_new_minisite_routes_other_action_ignored(): void
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
        $this->newMinisiteController
            ->expects($this->never())
            ->method('handleNewMinisite');

        $this->hooks->handleNewMinisiteRoutes();
    }

    /**
     * Test handleNewMinisiteRoutes terminates after handling
     */
    public function test_handle_new_minisite_routes_terminates_after_handling(): void
    {
        // Mock getQueryVar to return account route with 'new' action
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                if ($var === 'minisite_account') {
                    return '1';
                }
                if ($var === 'minisite_account_action') {
                    return 'new';
                }
                return '';
            });

        $this->newMinisiteController
            ->expects($this->once())
            ->method('handleNewMinisite');

        // The terminate() method is called (inherited from BaseHook)
        // TestTerminationHandler will prevent actual termination
        $this->hooks->handleNewMinisiteRoutes();

        // If we get here, the method executed successfully
        $this->assertTrue(true);
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        // No WordPress functions need to be mocked for these tests
        // All WordPress interactions go through WordPressNewMinisiteManager mock
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        // No cleanup needed
    }
}

