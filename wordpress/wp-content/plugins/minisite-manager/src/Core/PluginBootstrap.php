<?php

namespace Minisite\Core;

use Minisite\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

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
    private static ?callable $entityManagerFactory = null;
    private static ?callable $repositoryFactory = null;
    private static ?callable $loggerFactory = null;
    private static ?bool $doctrineAvailableOverride = null;
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
            if (! self::isDoctrineAvailable()) {
                // Doctrine not installed - skip initialization
                $logger = self::getBootstrapLogger();
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
                $GLOBALS['minisite_entity_manager'] = self::createEntityManager();
            }

            // Note: ConfigManager initialization is now handled by ConfigurationManagementFeature
            // The feature will initialize and store the service in GLOBALS for backward compatibility

            // Initialize ReviewRepository
            // Create ReviewRepository instance directly (same pattern as ConfigRepository)
            $em = $GLOBALS['minisite_entity_manager'];
            $reviewRepository = self::createRepository(
                \Minisite\Features\ReviewManagement\Repositories\ReviewRepository::class,
                $em,
                \Minisite\Features\ReviewManagement\Domain\Entities\Review::class
            );

            // Store in global for easy access
            $GLOBALS['minisite_review_repository'] = $reviewRepository;

            // Initialize VersionRepository
            // Create VersionRepository instance directly (same pattern as ReviewRepository)
            $versionRepository = self::createRepository(
                \Minisite\Features\VersionManagement\Repositories\VersionRepository::class,
                $em,
                \Minisite\Features\VersionManagement\Domain\Entities\Version::class
            );

            // Store in global for backward compatibility
            $GLOBALS['minisite_version_repository'] = $versionRepository;

            // Initialize MinisiteRepository
            // Create MinisiteRepository instance directly (same pattern as VersionRepository)
            $minisiteRepository = self::createRepository(
                \Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository::class,
                $em,
                \Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class
            );

            // Store in global for backward compatibility
            $GLOBALS['minisite_repository'] = $minisiteRepository;
        } catch (\Exception $e) {
            // Log error but don't fail initialization
            $logger = self::getBootstrapLogger();
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

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setEntityManagerFactory(?callable $factory): void
    {
        self::$entityManagerFactory = $factory;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setRepositoryFactory(?callable $factory): void
    {
        self::$repositoryFactory = $factory;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setLoggerFactory(?callable $factory): void
    {
        self::$loggerFactory = $factory;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setDoctrineAvailableOverride(?bool $isAvailable): void
    {
        self::$doctrineAvailableOverride = $isAvailable;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function resetTestState(): void
    {
        self::$entityManagerFactory = null;
        self::$repositoryFactory = null;
        self::$loggerFactory = null;
        self::$doctrineAvailableOverride = null;
    }

    private static function createEntityManager()
    {
        if (self::$entityManagerFactory !== null) {
            return call_user_func(self::$entityManagerFactory);
        }

        return \Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory::createEntityManager();
    }

    private static function createRepository(string $repositoryClass, $entityManager, string $entityClass)
    {
        if (self::$repositoryFactory !== null) {
            return call_user_func(self::$repositoryFactory, $repositoryClass, $entityManager, $entityClass);
        }

        $metadata = $entityManager->getClassMetadata($entityClass);

        return new $repositoryClass($entityManager, $metadata);
    }

    private static function getBootstrapLogger(): LoggerInterface
    {
        if (self::$loggerFactory !== null) {
            return call_user_func(self::$loggerFactory);
        }

        return LoggingServiceProvider::getFeatureLogger('plugin-bootstrap');
    }

    private static function isDoctrineAvailable(): bool
    {
        if (self::$doctrineAvailableOverride !== null) {
            return self::$doctrineAvailableOverride;
        }

        return class_exists(\Doctrine\ORM\EntityManager::class);
    }
}
