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
        add_action('init', [self::class, 'seedDefaultConfigs'], 15);
    }

    private static function runMigrations(): void
    {
        // Run Doctrine migrations first (for new tables like minisite_config)
        try {
            // Check if Doctrine is available before attempting migration
            if (!class_exists(\Doctrine\ORM\EntityManager::class)) {
                $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
                $logger->warning('Doctrine ORM not available - skipping migrations. Run: composer install');
                return;
            }
            
            $doctrineRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
            $doctrineRunner->migrate();
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Doctrine migrations failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Run custom migrations (for existing tables - to be migrated to Doctrine later)
        if (class_exists(\Minisite\Infrastructure\Versioning\VersioningController::class)) {
            $versioningController = new \Minisite\Infrastructure\Versioning\VersioningController(
                MINISITE_DB_VERSION,
                MINISITE_DB_OPTION
            );
            $versioningController->activate();
        }
    }
    
    /**
     * Seed default configurations
     * Called on 'init' hook after ConfigManager is initialized
     */
    public static function seedDefaultConfigs(): void
    {
        // Ensure ConfigManager is initialized first
        if (!isset($GLOBALS['minisite_config_manager'])) {
            // Try to initialize it now (might not have run yet)
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }
            
            // If still not available, retry on next init hook
            if (!isset($GLOBALS['minisite_config_manager'])) {
                // Prevent infinite loop - only retry once
                static $retryCount = 0;
                if ($retryCount < 2) {
                    $retryCount++;
                    add_action('init', [self::class, 'seedDefaultConfigs'], 20);
                }
                return;
            }
        }
        
        try {
            $seeder = new \Minisite\Infrastructure\Config\ConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Failed to seed default configs', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
