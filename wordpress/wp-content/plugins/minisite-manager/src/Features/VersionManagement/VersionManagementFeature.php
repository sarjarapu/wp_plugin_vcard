<?php

namespace Minisite\Features\VersionManagement;

/**
 * VersionManagement Feature Bootstrap
 * 
 * This class initializes the VersionManagement feature and registers
 * all necessary WordPress hooks and dependencies.
 */
class VersionManagementFeature
{
    /**
     * Initialize the VersionManagement feature
     */
    public static function initialize(): void
    {
        // Register WordPress hooks
        add_action('init', [self::class, 'registerHooks'], 10);
    }

    /**
     * Register WordPress hooks for the VersionManagement feature
     */
    public static function registerHooks(): void
    {
        $hooks = VersionHooksFactory::create();
        $hooks->register();
    }
}
