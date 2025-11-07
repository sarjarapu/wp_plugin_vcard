<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooks;
use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\Features\ConfigurationManagement\BaseConfigurationManagementIntegrationTest;

/**
 * Integration tests for ConfigurationManagementHooksFactory
 *
 * Tests the create() method which requires Doctrine EntityManager and database connection.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Database constants must be defined (handled by bootstrap.php)
 */
#[CoversClass(ConfigurationManagementHooksFactory::class)]
final class ConfigurationManagementHooksFactoryIntegrationTest extends BaseConfigurationManagementIntegrationTest
{
    /**
     * Test create returns ConfigurationManagementHooks instance
     */
    public function test_create_returns_hooks_instance(): void
    {
        $hooks = ConfigurationManagementHooksFactory::create();
        $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks);
    }

    /**
     * Test create returns same instance type on multiple calls
     */
    public function test_create_returns_consistent_instance(): void
    {
        $hooks1 = ConfigurationManagementHooksFactory::create();
        $hooks2 = ConfigurationManagementHooksFactory::create();

        $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks1);
        $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks2);
        // Note: They may be different instances, but should be same type
    }

    /**
     * Test create sets GLOBALS['minisite_config_manager']
     */
    public function test_create_sets_globals_config_manager(): void
    {
        // Clear GLOBALS before test
        unset($GLOBALS['minisite_config_manager']);

        $hooks = ConfigurationManagementHooksFactory::create();

        // Verify GLOBALS is set
        $this->assertArrayHasKey('minisite_config_manager', $GLOBALS);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService::class,
            $GLOBALS['minisite_config_manager']
        );
    }

    /**
     * Test create wires dependencies correctly using reflection
     */
    public function test_create_wires_dependencies_correctly(): void
    {
        $hooks = ConfigurationManagementHooksFactory::create();

        // Use reflection to verify dependencies are wired correctly
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

        // Verify controller has correct dependencies
        $controllerReflection = new \ReflectionClass($controller);
        $saveHandlerProperty = $controllerReflection->getProperty('saveHandler');
        $saveHandlerProperty->setAccessible(true);
        $saveHandler = $saveHandlerProperty->getValue($controller);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler::class,
            $saveHandler
        );

        $configServiceProperty = $controllerReflection->getProperty('configService');
        $configServiceProperty->setAccessible(true);
        $configService = $configServiceProperty->getValue($controller);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService::class,
            $configService
        );

        $rendererProperty = $controllerReflection->getProperty('renderer');
        $rendererProperty->setAccessible(true);
        $renderer = $rendererProperty->getValue($controller);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Rendering\ConfigurationManagementRenderer::class,
            $renderer
        );
    }
}
