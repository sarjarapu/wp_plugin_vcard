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
    }

    private static function runMigrations(): void
    {
        if (class_exists(\Minisite\Infrastructure\Versioning\VersioningController::class)) {
            $versioningController = new \Minisite\Infrastructure\Versioning\VersioningController(
                MINISITE_DB_VERSION,
                MINISITE_DB_OPTION
            );
            $versioningController->activate();
        }
    }
}
