<?php

namespace Minisite\Core;

use Psr\Log\LoggerInterface;

/**
 * Activation Handler
 *
 * SINGLE RESPONSIBILITY: Handle plugin activation
 * - Database migrations
 * - Role and capability setup
 * - Initial configuration
 */
final class ActivationHandler
{
    private static ?callable $migrationRunnerFactory = null;
    private static ?callable $configSeederFactory = null;
    private static ?callable $loggerFactory = null;
    private static ?callable $roleSyncCallback = null;
    private static int $configSeedRetryCount = 0;
    private static ?bool $doctrineAvailableOverride = null;
    public static function handle(): void
    {
        // Set flag to flush rewrite rules after init
        update_option('minisite_flush_rewrites', 1, false);

        // Run database migrations
        self::runMigrations();

        // Sync roles and capabilities
        self::syncRoles();

        // Seed default configurations (after migrations, before init)
        // Note: ConfigManager will be initialized in initializeCore()
        // So we delay seeding until init hook
        add_action('init', array(self::class, 'seedDefaultConfigs'), 15);
    }

    private static function runMigrations(): void
    {
        // Run Doctrine migrations (all tables are now managed by Doctrine)
        try {
            // Check if Doctrine is available before attempting migration
            if (! self::isDoctrineAvailable()) {
                $logger = self::getActivationLogger();
                $logger->warning('Doctrine ORM not available - skipping migrations. Run: composer install');

                return;
            }

            $doctrineRunner = self::createMigrationRunner();
            $doctrineRunner->migrate();

            // NOTE: Sample data seeding is now handled by individual migrations via seedSampleData() methods.
            // Each migration is responsible for seeding its own sample data after table creation.
        } catch (\Exception $e) {
            // Log error with full details
            $logger = self::getActivationLogger();
            $logger->error('Doctrine migrations failed', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }


    /**
     * Seed default configurations
     * Called on 'init' hook after ConfigManager is initialized
     */
    public static function seedDefaultConfigs(): void
    {
        // Ensure ConfigManager is initialized first
        if (! isset($GLOBALS['minisite_config_manager'])) {
            // Try to initialize it now (might not have run yet)
            if (self::isDoctrineAvailable()) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }

            // If still not available, retry on next init hook
            if (! isset($GLOBALS['minisite_config_manager'])) {
                if (self::$configSeedRetryCount < 2) {
                    self::$configSeedRetryCount++;
                    add_action('init', array(self::class, 'seedDefaultConfigs'), 20);
                }

                return;
            }
        }

        try {
            $seeder = self::createConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
            self::$configSeedRetryCount = 0;
        } catch (\Exception $e) {
            // Log error with full details
            $logger = self::getActivationLogger();
            $logger->error('Failed to seed default configs', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setMigrationRunnerFactory(?callable $factory): void
    {
        self::$migrationRunnerFactory = $factory;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setConfigSeederFactory(?callable $factory): void
    {
        self::$configSeederFactory = $factory;
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
    public static function setRoleSyncCallback(?callable $callback): void
    {
        self::$roleSyncCallback = $callback;
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
        self::$migrationRunnerFactory = null;
        self::$configSeederFactory = null;
        self::$loggerFactory = null;
        self::$roleSyncCallback = null;
        self::$configSeedRetryCount = 0;
        self::$doctrineAvailableOverride = null;
    }

    private static function createMigrationRunner(): object
    {
        if (self::$migrationRunnerFactory !== null) {
            return call_user_func(self::$migrationRunnerFactory);
        }

        return new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
    }

    private static function createConfigSeeder(): \Minisite\Features\ConfigurationManagement\Services\ConfigSeeder
    {
        if (self::$configSeederFactory !== null) {
            return call_user_func(self::$configSeederFactory);
        }

        return new \Minisite\Features\ConfigurationManagement\Services\ConfigSeeder();
    }

    private static function getActivationLogger(): LoggerInterface
    {
        if (self::$loggerFactory !== null) {
            return call_user_func(self::$loggerFactory);
        }

        return \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
    }

    private static function syncRoles(): void
    {
        if (self::$roleSyncCallback !== null) {
            call_user_func(self::$roleSyncCallback);

            return;
        }

        RoleManager::syncRolesAndCapabilities();
    }

    private static function isDoctrineAvailable(): bool
    {
        if (self::$doctrineAvailableOverride !== null) {
            return self::$doctrineAvailableOverride;
        }

        return class_exists(\Doctrine\ORM\EntityManager::class);
    }
}
