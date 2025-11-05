<?php

namespace Minisite\Features\PublishMinisite;

use Minisite\Features\PublishMinisite\Hooks\PublishHooksFactory;

/**
 * PublishMinisite Feature Bootstrap
 *
 * SINGLE RESPONSIBILITY: Bootstrap the PublishMinisite feature
 * - Initializes the minisite publishing system
 * - Registers all publishing hooks
 * - Provides a clean entry point for the feature
 */
final class PublishMinisiteFeature
{
    /**
     * Initialize the PublishMinisite feature
     */
    public static function initialize(): void
    {
        $publishHooks = PublishHooksFactory::create();
        $publishHooks->register();

        // Register template_redirect handler with priority 4 (after NewMinisite priority 2, Edit priority 3)
        add_action('template_redirect', array($publishHooks, 'handlePublishRoutes'), 4);
    }
}
