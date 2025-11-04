<?php

namespace Minisite\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooksFactory;

/**
 * ConfigurationManagement Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the ConfigurationManagement feature
 * - Initializes the application configuration system
 * - Registers all configuration-related hooks
 * - Provides a clean entry point for the feature
 */
final class ConfigurationManagementFeature
{
    /**
     * Initialize the ConfigurationManagement feature
     */
    public static function initialize(): void
    {
        $hooks = ConfigurationManagementHooksFactory::create();
        $hooks->register();
    }
}
