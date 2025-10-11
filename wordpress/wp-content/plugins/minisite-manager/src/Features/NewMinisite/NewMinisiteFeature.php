<?php

namespace Minisite\Features\NewMinisite;

use Minisite\Features\NewMinisite\Hooks\NewMinisiteHooksFactory;

/**
 * NewMinisite Feature Bootstrap
 *
 * SINGLE RESPONSIBILITY: Bootstrap the NewMinisite feature
 * - Initializes the new minisite creation system
 * - Registers all creation hooks
 * - Provides a clean entry point for the feature
 */
final class NewMinisiteFeature
{
    /**
     * Initialize the NewMinisite feature
     */
    public static function initialize(): void
    {
        $newMinisiteHooks = NewMinisiteHooksFactory::create();
        $newMinisiteHooks->register();

        // Register template_redirect handler with priority 2 to run before EditFeature (priority 3)
        add_action('template_redirect', [$newMinisiteHooks, 'handleNewMinisiteRoutes'], 2);
    }
}
