<?php

namespace Minisite\Features\VersionManagement;

use Minisite\Features\VersionManagement\Hooks\VersionHooksFactory;

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
        $hooks = VersionHooksFactory::create();
        $hooks->register();
        
        // Register template_redirect handler immediately
        add_action('template_redirect', [$hooks, 'handleVersionHistoryPage'], 5);
    }
}
