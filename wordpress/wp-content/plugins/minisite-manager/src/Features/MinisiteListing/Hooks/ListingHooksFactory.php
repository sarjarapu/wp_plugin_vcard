<?php

namespace Minisite\Features\MinisiteListing\Hooks;

use Minisite\Features\MinisiteListing\Controllers\ListingController;
use Minisite\Features\MinisiteListing\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteListing\Http\ListingRequestHandler;
use Minisite\Features\MinisiteListing\Http\ListingResponseHandler;
use Minisite\Features\MinisiteListing\Rendering\ListingRenderer;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;

/**
 * ListingHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure ListingHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete listing system
 */
final class ListingHooksFactory
{
    /**
     * Create and configure ListingHooks
     */
    public static function create(): ListingHooks
    {
        // Create services
        $listingManager = new WordPressListingManager();
        $listingService = new MinisiteListingService($listingManager);

        // Create handlers
        $listMinisitesHandler = new ListMinisitesHandler($listingService);

        // Create additional dependencies for refactored controllers
        $requestHandler = new ListingRequestHandler($listingManager);
        $responseHandler = new ListingResponseHandler($listingManager);
        $renderer = new ListingRenderer();

        // Create controllers
        $listingController = new ListingController(
            $listMinisitesHandler,
            $listingService,
            $requestHandler,
            $responseHandler,
            $renderer,
            $listingManager
        );

        // Create and return hooks
        return new ListingHooks($listingController);
    }
}
