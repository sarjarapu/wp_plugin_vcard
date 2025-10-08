<?php

namespace Minisite\Core;

/**
 * Deactivation Handler
 * 
 * SINGLE RESPONSIBILITY: Handle plugin deactivation
 * - Cleanup tasks
 * - Scheduled events cleanup
 * - Optional data cleanup in non-production
 */
final class DeactivationHandler
{
    public static function handle(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Cleanup in non-production
        if (!MINISITE_LIVE_PRODUCTION) {
            self::cleanupNonProduction();
        }
    }
    
    private static function cleanupNonProduction(): void
    {
        // Drop plugin tables
        if (class_exists(\Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase::class)) {
            $migration = new \Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase();
            $migration->down();
        }
        
        // Clear version option
        delete_option(MINISITE_DB_OPTION);
        
        // Remove custom roles
        foreach (['minisite_user', 'minisite_member', 'minisite_power', 'minisite_admin'] as $roleSlug) {
            remove_role($roleSlug);
        }
    }
}
