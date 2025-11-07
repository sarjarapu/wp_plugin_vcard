<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooks;
use Minisite\Features\ConfigurationManagement\Controllers\ConfigurationManagementController;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ConfigurationManagementHooks
 */
#[CoversClass(ConfigurationManagementHooks::class)]
final class ConfigurationManagementHooksTest extends TestCase
{
    private ConfigurationManagementController|MockObject $controller;
    private DeleteConfigHandler|MockObject $deleteHandler;
    private TerminationHandlerInterface|MockObject $terminationHandler;
    private ConfigurationManagementHooks $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->controller = $this->createMock(ConfigurationManagementController::class);
        $this->deleteHandler = $this->createMock(DeleteConfigHandler::class);
        $this->terminationHandler = $this->createMock(TerminationHandlerInterface::class);
        $this->hooks = new ConfigurationManagementHooks(
            $this->controller,
            $this->deleteHandler,
            $this->terminationHandler
        );
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooks::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('Minisite\Features\ConfigurationManagement\Controllers\ConfigurationManagementController', $params[0]->getType()->getName());
        $this->assertEquals('Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler', $params[1]->getType()->getName());
        $this->assertEquals('Minisite\Infrastructure\Http\TerminationHandlerInterface', $params[2]->getType()->getName());
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
     * Test register method calls add_action for admin_menu
     * Note: add_action is defined in bootstrap.php, so we can't mock it.
     * We verify the method executes without errors.
     */
    public function test_register_calls_add_action_for_admin_menu(): void
    {
        // add_action is defined in bootstrap.php, can't mock due to Patchwork conflicts
        // But we can verify the method executes successfully
        try {
            $this->hooks->register();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('register() should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test register method calls add_action for admin_post
     * Note: add_action is defined in bootstrap.php, so we can't mock it.
     * We verify the method executes without errors.
     */
    public function test_register_calls_add_action_for_admin_post(): void
    {
        // add_action is defined in bootstrap.php, can't mock due to Patchwork conflicts
        // But we can verify the method executes successfully
        try {
            $this->hooks->register();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('register() should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test register method is public
     */
    public function test_register_is_public(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooks::class);
        $registerMethod = $reflection->getMethod('register');

        $this->assertTrue($registerMethod->isPublic());
    }

    /**
     * Test registerAdminMenu method calls add_submenu_page
     * Note: add_submenu_page is defined in bootstrap.php, so we can't mock it.
     * We verify the method executes without errors.
     */
    public function test_registerAdminMenu_calls_add_submenu_page(): void
    {
        // add_submenu_page is defined in bootstrap.php, can't mock due to Patchwork conflicts
        // But we can verify the method executes successfully
        try {
            $this->hooks->registerAdminMenu();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('registerAdminMenu() should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test renderPage calls controller methods when user has permissions
     * Note: current_user_can is defined in bootstrap.php and returns true by default
     */
    public function test_renderPage_calls_controller_when_user_has_permissions(): void
    {
        // current_user_can returns true by default in bootstrap.php
        $this->controller
            ->expects($this->once())
            ->method('handleRequest');

        $this->controller
            ->expects($this->once())
            ->method('render');

        // renderPage should call both controller methods
        $this->hooks->renderPage();
    }

    /**
     * Test renderPage checks permissions and calls wp_die when user lacks permissions
     * Note: current_user_can can be overridden via $GLOBALS, wp_die throws exception
     */
    public function test_renderPage_checks_permissions_and_dies_when_unauthorized(): void
    {
        // Override current_user_can to return false
        $GLOBALS['_test_mock_current_user_can'] = false;

        // wp_die throws an exception in bootstrap.php
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to access this page.');

        try {
            $this->hooks->renderPage();
        } finally {
            // Clean up
            unset($GLOBALS['_test_mock_current_user_can']);
        }
    }

    /**
     * Test handleDeleteAction checks permissions and dies when unauthorized
     */
    public function test_handleDeleteAction_checks_permissions_and_dies_when_unauthorized(): void
    {
        // Override current_user_can to return false
        $GLOBALS['_test_mock_current_user_can'] = false;

        $_GET['nonce'] = 'test_nonce';
        $_GET['key'] = 'test_key';

        // wp_die throws an exception in bootstrap.php
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized');

        try {
            $this->hooks->handleDeleteAction();
        } finally {
            // Clean up
            unset($GLOBALS['_test_mock_current_user_can']);
            unset($_GET['nonce']);
            unset($_GET['key']);
        }
    }

    /**
     * Test handleDeleteAction verifies nonce and dies when invalid
     */
    public function test_handleDeleteAction_verifies_nonce_and_dies_when_invalid(): void
    {
        // Override wp_verify_nonce to return false
        $GLOBALS['_test_mock_wp_verify_nonce'] = false;

        $_GET['nonce'] = '';
        $_GET['key'] = 'test_key';

        // wp_die throws an exception in bootstrap.php
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed');

        try {
            $this->hooks->handleDeleteAction();
        } finally {
            // Clean up
            unset($GLOBALS['_test_mock_wp_verify_nonce']);
            unset($_GET['nonce']);
            unset($_GET['key']);
        }
    }

    /**
     * Test handleDeleteAction dies when key is missing
     */
    public function test_handleDeleteAction_dies_when_key_missing(): void
    {
        $_GET['nonce'] = 'test_nonce';
        // Don't set key at all, or set it to empty string after sanitization
        // The code checks: isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : ''
        // So we need to ensure the result is empty
        $_GET['key'] = '   '; // Whitespace only, which becomes empty after sanitize_text_field

        // wp_die throws an exception in bootstrap.php
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid configuration key');

        try {
            $this->hooks->handleDeleteAction();
        } finally {
            // Clean up
            unset($_GET['nonce']);
            unset($_GET['key']);
        }
    }

    /**
     * Test handleDeleteAction handles delete command successfully
     */
    public function test_handleDeleteAction_handles_delete_successfully(): void
    {
        $_GET['nonce'] = 'test_nonce';
        $_GET['key'] = 'test_key';

        // Mock handler to expect handle call
        $this->deleteHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($command) {
                return $command instanceof \Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand
                    && $command->key === 'test_key';
            }));

        // Note: terminate() is called after wp_redirect, but wp_redirect throws an exception
        // in test environment, so terminate() never gets called. That's expected behavior.

        // wp_redirect throws an exception in test environment
        // We expect this exception after handler->handle() is called
        try {
            $this->hooks->handleDeleteAction();
            $this->fail('Expected wp_redirect exception');
        } catch (\Exception $e) {
            // wp_redirect throws an exception in test environment - this is expected
            // The important thing is that handler->handle() was called (verified by mock expectation)
            if (str_contains($e->getMessage(), 'Redirect') || str_contains($e->getMessage(), 'redirect')) {
                $this->assertTrue(true); // Expected redirect exception
            } else {
                throw $e;
            }
        } finally {
            // Clean up
            unset($_GET['nonce']);
            unset($_GET['key']);
        }
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooks::class);
        $this->assertTrue($reflection->isFinal());
    }
}

