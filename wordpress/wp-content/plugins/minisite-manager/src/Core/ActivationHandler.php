<?php

namespace Minisite\Core;

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
    public static function handle(): void
    {
        // Set flag to flush rewrite rules after init
        update_option('minisite_flush_rewrites', 1, false);

        // Run database migrations
        self::runMigrations();

        // Sync roles and capabilities
        RoleManager::syncRolesAndCapabilities();

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
            if (! class_exists(\Doctrine\ORM\EntityManager::class)) {
                $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
                $logger->warning('Doctrine ORM not available - skipping migrations. Run: composer install');

                return;
            }

            $doctrineRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
            $doctrineRunner->migrate();

            // NOTE: Sample data seeding is now handled by individual migrations via seedSampleData() methods.
            // Each migration is responsible for seeding its own sample data after table creation.
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
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
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }

            // If still not available, retry on next init hook
            if (! isset($GLOBALS['minisite_config_manager'])) {
                // Prevent infinite loop - only retry once
                static $retryCount = 0;
                if ($retryCount < 2) {
                    $retryCount++;
                    add_action('init', array(self::class, 'seedDefaultConfigs'), 20);
                }

                return;
            }
        }

        try {
            $seeder = new \Minisite\Features\ConfigurationManagement\Services\ConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Failed to seed default configs', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }
}
