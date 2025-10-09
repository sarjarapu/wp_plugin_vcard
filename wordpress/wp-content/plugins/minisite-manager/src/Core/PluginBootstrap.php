<?php

namespace Minisite\Core;

/**
 * Plugin Bootstrap
 *
 * SINGLE RESPONSIBILITY: Initialize the plugin and coordinate core systems
 * - Handles plugin lifecycle (activation/deactivation)
 * - Initializes core systems (roles, capabilities, features)
 * - Coordinates between different plugin components
 */
final class PluginBootstrap
{
    public static function initialize(): void
    {
        // Register activation/deactivation hooks
        register_activation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onActivation']);
        register_deactivation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onDeactivation']);

        // Initialize core systems
        add_action('init', [self::class, 'initializeCore'], 5);

        // Initialize features
        add_action('init', [self::class, 'initializeFeatures'], 10);
    }

    public static function onActivation(): void
    {
        ActivationHandler::handle();
    }

    public static function onDeactivation(): void
    {
        DeactivationHandler::handle();
    }

    public static function initializeCore(): void
    {
        // Initialize roles and capabilities
        RoleManager::initialize();

        // Initialize rewrite rules
        RewriteCoordinator::initialize();

        // Initialize admin menu
        AdminMenuManager::initialize();
    }

    public static function initializeFeatures(): void
    {
        FeatureRegistry::initializeAll();
    }
}
