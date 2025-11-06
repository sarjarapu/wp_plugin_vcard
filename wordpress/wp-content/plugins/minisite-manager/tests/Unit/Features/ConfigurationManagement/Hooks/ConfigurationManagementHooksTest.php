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
     * Test register method can be called without errors
     */
    public function test_register_can_be_called(): void
    {
        \Brain\Monkey\Functions\expect('add_action')
            ->twice()
            ->withAnyArgs();

        $this->hooks->register();
        $this->assertTrue(true); // If we get here, no exception was thrown
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
     * Test registerAdminMenu method exists
     */
    public function test_registerAdminMenu_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'registerAdminMenu'));
    }

    /**
     * Test renderPage method calls controller
     */
    public function test_renderPage_calls_controller(): void
    {
        \Brain\Monkey\Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_submenu_page')
            ->once()
            ->andReturn('hook_page');

        $this->controller
            ->expects($this->once())
            ->method('handleRequest');

        $this->controller
            ->expects($this->once())
            ->method('render');

        $this->hooks->renderPage();
    }

    /**
     * Test renderPage checks permissions
     */
    public function test_renderPage_checks_permissions(): void
    {
        \Brain\Monkey\Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        \Brain\Monkey\Functions\expect('wp_die')
            ->once()
            ->with('You do not have permission to access this page.');

        $this->hooks->renderPage();
    }

    /**
     * Test handleDeleteAction verifies nonce
     */
    public function test_handleDeleteAction_verifies_nonce(): void
    {
        $_GET['nonce'] = 'invalid_nonce';
        $_GET['key'] = 'test_key';

        \Brain\Monkey\Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('wp_unslash')
            ->andReturnUsing(fn($value) => $value);

        \Brain\Monkey\Functions\expect('sanitize_text_field')
            ->andReturnUsing(fn($value) => $value);

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->with('invalid_nonce', 'minisite_config_delete')
            ->andReturn(false);

        \Brain\Monkey\Functions\expect('wp_die')
            ->once()
            ->with('Security check failed');

        $this->hooks->handleDeleteAction();
    }

    /**
     * Test handleDeleteAction handles delete command
     */
    public function test_handleDeleteAction_handles_delete(): void
    {
        $_GET['nonce'] = 'valid_nonce';
        $_GET['key'] = 'test_key';

        \Brain\Monkey\Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('wp_unslash')
            ->andReturnUsing(fn($value) => $value);

        \Brain\Monkey\Functions\expect('sanitize_text_field')
            ->andReturnUsing(fn($value) => $value);

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'minisite_config_delete')
            ->andReturn(true);

        $this->deleteHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($command) {
                return $command->key === 'test_key';
            }));

        \Brain\Monkey\Functions\expect('add_query_arg')
            ->once()
            ->andReturn('admin.php?page=minisite-config&deleted=1');

        \Brain\Monkey\Functions\expect('admin_url')
            ->once()
            ->with('admin.php')
            ->andReturn('http://example.com/wp-admin/admin.php');

        \Brain\Monkey\Functions\expect('wp_redirect')
            ->once();

        $this->terminationHandler
            ->expects($this->once())
            ->method('terminate');

        $this->hooks->handleDeleteAction();
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

