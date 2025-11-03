<?php

namespace Minisite\Core;

use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;

/**
 * Plugin Bootstrap
 *
 * SINGLE RESPONSIBILITY: Initialize the plugin and coordinate core systems
 * - Handles plugin lifecycle (activation/deactivation)
 * - Initializes core systems (roles, capabilities, features)
 * - Coordinates between different plugin components
 */
final class PluginBootstrap
{
    public static function initialize(): void
    {
        // Register activation/deactivation hooks
        register_activation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onActivation']);
        register_deactivation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onDeactivation']);

        // Initialize core systems
        add_action('init', [self::class, 'initializeCore'], 5);

        // Initialize features
        add_action('init', [self::class, 'initializeFeatures'], 10);
    }

    public static function onActivation(): void
    {
        ActivationHandler::handle();
    }

    public static function onDeactivation(): void
    {
        DeactivationHandler::handle();
    }

    public static function initializeCore(): void
    {
        // Initialize logging system first
        LoggingServiceProvider::register();

        // Initialize error handling system
        ErrorHandlingServiceProvider::register();

        // Initialize roles and capabilities
        RoleManager::initialize();

        // Initialize rewrite rules
        RewriteCoordinator::initialize();

        // Initialize admin menu
        AdminMenuManager::initialize();
        
        // Initialize Doctrine and Config Manager
        self::initializeConfigSystem();
    }
    
    /**
     * Initialize configuration management system
     * Public so it can be called from ActivationHandler if needed
     */
    public static function initializeConfigSystem(): void
    {
        try {
            // Check if Doctrine is available
            if (!class_exists(\Doctrine\ORM\EntityManager::class)) {
                // Doctrine not installed - skip initialization
                $logger = LoggingServiceProvider::getFeatureLogger('plugin-bootstrap');
                $logger->warning('Doctrine ORM not available - ConfigManager will not be initialized');
                return;
            }
            
            // Initialize Doctrine EntityManager
            if (!isset($GLOBALS['minisite_entity_manager'])) {
                $GLOBALS['minisite_entity_manager'] = \Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory::createEntityManager();
            }
            
            // Initialize ConfigManager
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $GLOBALS['minisite_entity_manager'];
            
            // Create ConfigRepository instance directly
            // Note: We can't use $em->getRepository() because it returns default EntityRepository
            // We need our custom ConfigRepository that implements ConfigRepositoryInterface
            $configRepository = new \Minisite\Infrastructure\Persistence\Repositories\ConfigRepository(
                $em,
                $em->getClassMetadata(\Minisite\Domain\Entities\Config::class)
            );
            
            $configManager = new \Minisite\Domain\Services\ConfigManager($configRepository);
            
            // Store in global for easy access
            $GLOBALS['minisite_config_manager'] = $configManager;
            
            // Register admin menu for config management
            if (is_admin()) {
                \Minisite\Features\AppConfig\WordPress\ConfigAdminMenu::register();
            }
        } catch (\Exception $e) {
            // Log error but don't fail initialization
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('plugin-bootstrap');
            $logger->error('Failed to initialize config system', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public static function initializeFeatures(): void
    {
        FeatureRegistry::initializeAll();
    }
}
