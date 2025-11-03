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
            $doctrineRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
            $doctrineRunner->migrate();
        } catch (\Exception $e) {
            // Log error but continue with custom migrations
            if (function_exists('error_log')) {
                error_log('Doctrine migrations failed: ' . $e->getMessage());
            }
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
        if (!isset($GLOBALS['minisite_config_manager'])) {
            return; // ConfigManager not initialized yet
        }
        
        try {
            $seeder = new \Minisite\Infrastructure\Config\ConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
        } catch (\Exception $e) {
            // Log error but don't fail activation
            if (function_exists('error_log')) {
                error_log('Failed to seed default configs: ' . $e->getMessage());
            }
        }
    }
}
