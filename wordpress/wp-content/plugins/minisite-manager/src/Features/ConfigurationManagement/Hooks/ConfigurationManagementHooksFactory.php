<?php

namespace Minisite\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Features\ConfigurationManagement\Controllers\ConfigurationManagementController;
use Minisite\Features\ConfigurationManagement\Rendering\ConfigurationManagementRenderer;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

/**
 * ConfigurationManagementHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure ConfigurationManagementHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and repositories
 * - Configures the complete configuration management system
 */
final class ConfigurationManagementHooksFactory
{
    /**
     * Create and configure ConfigurationManagementHooks
     */
    public static function create(): ConfigurationManagementHooks
    {
        // Create EntityManager (following pattern from Authentication/ReviewManagement features)
        $em = DoctrineFactory::createEntityManager();
        $configRepository = new ConfigRepository(
            $em,
            $em->getClassMetadata(\Minisite\Features\ConfigurationManagement\Domain\Entities\Config::class)
        );

        // Create service
        $configService = new ConfigurationManagementService($configRepository);

        // Create handlers
        $saveHandler = new SaveConfigHandler($configService);
        $deleteHandler = new DeleteConfigHandler($configService);

        // Create renderer
        $renderer = new ConfigurationManagementRenderer();

        // Create controller
        $controller = new ConfigurationManagementController(
            $saveHandler,
            $deleteHandler,
            $configService,
            $renderer
        );

        // Store service in GLOBALS for backward compatibility with legacy code
        // This is a temporary bridge until ActivationHandler and ConfigSeeder are refactored
        // TODO: Remove GLOBALS usage once all legacy code is updated to use dependency injection
        // See: src/Core/ActivationHandler.php and src/Features/ConfigurationManagement/Services/ConfigSeeder.php
        $GLOBALS['minisite_config_manager'] = $configService;

        // Create and return hooks
        return new ConfigurationManagementHooks($controller, $deleteHandler);
    }
}
