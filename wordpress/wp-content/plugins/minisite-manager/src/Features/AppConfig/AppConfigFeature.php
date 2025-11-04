<?php

namespace Minisite\Features\AppConfig;

use Minisite\Features\AppConfig\Hooks\AppConfigHooksFactory;

/**
 * AppConfig Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the AppConfig feature
 * - Initializes the application configuration system
 * - Registers all configuration-related hooks
 * - Provides a clean entry point for the feature
 */
final class AppConfigFeature
{
    /**
     * Initialize the AppConfig feature
     */
    public static function initialize(): void
    {
        $hooks = AppConfigHooksFactory::create();
        $hooks->register();
    }
}
