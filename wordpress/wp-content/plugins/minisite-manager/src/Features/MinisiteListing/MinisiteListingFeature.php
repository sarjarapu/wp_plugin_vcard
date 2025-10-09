<?php

namespace Minisite\Features\MinisiteListing;

use Minisite\Features\MinisiteListing\Hooks\ListingHooksFactory;

/**
 * MinisiteListing Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the MinisiteListing feature
 * - Initializes the minisite listing system
 * - Registers all listing hooks
 * - Provides a clean entry point for the feature
 */
final class MinisiteListingFeature
{
    /**
     * Initialize the MinisiteListing feature
     */
    public static function initialize(): void
    {
        $listingHooks = ListingHooksFactory::create();
        $listingHooks->register();
        
        // Register template_redirect handler immediately
        add_action('template_redirect', [$listingHooks, 'handleListingRoutes'], 5);
    }
}
