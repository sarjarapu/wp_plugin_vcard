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
        if (! defined('MINISITE_LIVE_PRODUCTION') || ! MINISITE_LIVE_PRODUCTION) {
            self::cleanupNonProduction();
        }
    }

    private static function cleanupNonProduction(): void
    {
        // NOTE: Legacy migration system (_1_0_0_CreateBase) has been replaced by Doctrine migrations.
        // Table cleanup is now handled by Doctrine migrations if needed.
        // For non-production cleanup, tables are typically left in place for development convenience.

        // Clear legacy version option (if it exists)
        delete_option(MINISITE_DB_OPTION);

        // Remove custom roles
        foreach (array('minisite_user', 'minisite_member', 'minisite_power', 'minisite_admin') as $roleSlug) {
            remove_role($roleSlug);
        }
    }
}
