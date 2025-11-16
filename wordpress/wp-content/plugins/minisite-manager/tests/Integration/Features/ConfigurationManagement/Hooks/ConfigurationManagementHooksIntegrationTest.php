<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooks;
use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for ConfigurationManagementHooks
 *
 * Tests the hooks class with real dependencies created via the factory.
 * This covers functionality that requires database connection and real object wiring.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ConfigurationManagementHooks::class)]
final class ConfigurationManagementHooksIntegrationTest extends BaseIntegrationTest
{
    /**
     * Get entity paths for ORM configuration
     * Note: This test doesn't directly use EntityManager, but BaseIntegrationTest requires it
     */
    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    /**
     * Setup test-specific services
     * Note: This test uses factory methods that create their own dependencies
     */
    protected function setupTestSpecificServices(): void
    {
        // No specific services needed - tests use factory methods
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void
    {
        // No test data to clean up - tests only verify hook registration
    }

    /**
     * Test register() registers WordPress hooks correctly
     */
    public function test_register_registers_wordpress_hooks(): void
    {
        // Create hooks via factory (requires DB)
        $hooks = ConfigurationManagementHooksFactory::create();

        // Clear any existing hooks
        global $wp_filter;
        $wp_filter = null;

        // Call register
        $hooks->register();

        // Verify hooks were registered
        $this->assertNotNull($wp_filter, '$wp_filter should be initialized');
        $this->assertObjectHasProperty('callbacks', $wp_filter);
        $this->assertArrayHasKey('admin_menu', $wp_filter->callbacks);
        $this->assertArrayHasKey('admin_post_minisite_config_delete', $wp_filter->callbacks);

        // Verify callbacks are not empty
        $adminMenuHooks = $wp_filter->callbacks['admin_menu'] ?? array();
        $this->assertNotEmpty($adminMenuHooks, 'admin_menu hook should be registered');

        $adminPostHooks = $wp_filter->callbacks['admin_post_minisite_config_delete'] ?? array();
        $this->assertNotEmpty($adminPostHooks, 'admin_post_minisite_config_delete hook should be registered');
    }

    /**
     * Test registerAdminMenu() can be called with real dependencies
     */
    public function test_registerAdminMenu_can_be_called_with_real_dependencies(): void
    {
        // Create hooks via factory (requires DB)
        $hooks = ConfigurationManagementHooksFactory::create();

        // Call registerAdminMenu - should not throw
        $hooks->registerAdminMenu();

        // Verify it completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test hooks instance has correct dependencies wired via factory
     */
    public function test_hooks_has_correct_dependencies_wired(): void
    {
        // Create hooks via factory
        $hooks = ConfigurationManagementHooksFactory::create();

        // Use reflection to verify dependencies
        $reflection = new \ReflectionClass($hooks);

        // Verify controller is set
        $controllerProperty = $reflection->getProperty('controller');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($hooks);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Controllers\ConfigurationManagementController::class,
            $controller
        );

        // Verify deleteHandler is set
        $deleteHandlerProperty = $reflection->getProperty('deleteHandler');
        $deleteHandlerProperty->setAccessible(true);
        $deleteHandler = $deleteHandlerProperty->getValue($hooks);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler::class,
            $deleteHandler
        );
    }
}
