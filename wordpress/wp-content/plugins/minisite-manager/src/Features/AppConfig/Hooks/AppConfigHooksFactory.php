<?php

namespace Minisite\Features\AppConfig\Hooks;

use Minisite\Features\AppConfig\Repositories\ConfigRepository;
use Minisite\Features\AppConfig\Services\AppConfigService;
use Minisite\Features\AppConfig\Handlers\SaveConfigHandler;
use Minisite\Features\AppConfig\Handlers\DeleteConfigHandler;
use Minisite\Features\AppConfig\Controllers\AppConfigController;
use Minisite\Features\AppConfig\Rendering\AppConfigRenderer;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

/**
 * AppConfigHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure AppConfigHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and repositories
 * - Configures the complete configuration management system
 */
final class AppConfigHooksFactory
{
    /**
     * Create and configure AppConfigHooks
     */
    public static function create(): AppConfigHooks
    {
        // Create EntityManager (following pattern from Authentication/ReviewManagement features)
        $em = DoctrineFactory::createEntityManager();
        $configRepository = new ConfigRepository(
            $em,
            $em->getClassMetadata(\Minisite\Features\AppConfig\Domain\Entities\Config::class)
        );

        // Create service
        $configService = new AppConfigService($configRepository);

        // Create handlers
        $saveHandler = new SaveConfigHandler($configService);
        $deleteHandler = new DeleteConfigHandler($configService);

        // Create renderer
        $renderer = new AppConfigRenderer();

        // Create controller
        $controller = new AppConfigController(
            $saveHandler,
            $deleteHandler,
            $configService,
            $renderer
        );

        // Store service in GLOBALS for backward compatibility with legacy code
        // This is a temporary bridge until ActivationHandler and ConfigSeeder are refactored
        // TODO: Remove GLOBALS usage once all legacy code is updated to use dependency injection
        // See: src/Core/ActivationHandler.php and src/Infrastructure/Config/ConfigSeeder.php
        $GLOBALS['minisite_config_manager'] = $configService;

        // Create and return hooks
        return new AppConfigHooks($controller, $deleteHandler);
    }
}
