<?php

namespace Minisite\Core;

use Minisite\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;

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
        register_activation_hook(MINISITE_PLUGIN_FILE, array(self::class, 'onActivation'));
        register_deactivation_hook(MINISITE_PLUGIN_FILE, array(self::class, 'onDeactivation'));

        // Initialize core systems
        add_action('init', array(self::class, 'initializeCore'), 5);

        // Initialize features
        add_action('init', array(self::class, 'initializeFeatures'), 10);
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
            if (! class_exists(\Doctrine\ORM\EntityManager::class)) {
                // Doctrine not installed - skip initialization
                $logger = LoggingServiceProvider::getFeatureLogger('plugin-bootstrap');
                $logger->warning('Doctrine ORM not available - ConfigManager will not be initialized');

                return;
            }

            // Initialize Doctrine EntityManager
            // Check if EntityManager exists and is still open
            $needsNewEm = true;
            if (isset($GLOBALS['minisite_entity_manager'])) {
                try {
                    // Try to use the EntityManager - if it's closed, this will throw an exception
                    $GLOBALS['minisite_entity_manager']->getConnection();
                    $needsNewEm = false; // EntityManager is valid and open
                } catch (\Doctrine\ORM\Exception\EntityManagerClosed $e) {
                    // EntityManager is closed - need to create a new one
                    $needsNewEm = true;
                    // Clear the closed EntityManager
                    unset($GLOBALS['minisite_entity_manager']);
                }
            }

            if ($needsNewEm) {
                $GLOBALS['minisite_entity_manager'] =
                    \Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory::createEntityManager();
            }

            // Note: ConfigManager initialization is now handled by ConfigurationManagementFeature
            // The feature will initialize and store the service in GLOBALS for backward compatibility

            // Initialize ReviewRepository
            // Create ReviewRepository instance directly (same pattern as ConfigRepository)
            $em = $GLOBALS['minisite_entity_manager'];
            $reviewRepository = new \Minisite\Features\ReviewManagement\Repositories\ReviewRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\ReviewManagement\Domain\Entities\Review::class)
            );

            // Store in global for easy access
            $GLOBALS['minisite_review_repository'] = $reviewRepository;

            // Initialize VersionRepository
            // Create VersionRepository instance directly (same pattern as ReviewRepository)
            $versionRepository = new \Minisite\Features\VersionManagement\Repositories\VersionRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\VersionManagement\Domain\Entities\Version::class)
            );

            // Store in global for backward compatibility
            $GLOBALS['minisite_version_repository'] = $versionRepository;

            // Initialize MinisiteRepository
            // Create MinisiteRepository instance directly (same pattern as VersionRepository)
            $minisiteRepository = new \Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class)
            );

            // Store in global for backward compatibility
            $GLOBALS['minisite_repository'] = $minisiteRepository;
        } catch (\Exception $e) {
            // Log error but don't fail initialization
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('plugin-bootstrap');
            $logger->error('Failed to initialize config system', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ));
        }
    }

    public static function initializeFeatures(): void
    {
        FeatureRegistry::initializeAll();
    }
}
