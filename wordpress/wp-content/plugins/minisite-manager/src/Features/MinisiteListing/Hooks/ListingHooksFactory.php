<?php

namespace Minisite\Features\MinisiteListing\Hooks;

use Minisite\Features\MinisiteListing\Controllers\ListingController;
use Minisite\Features\MinisiteListing\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

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
        global $wpdb;

        // Create repositories
        $minisiteRepository = new MinisiteRepository($wpdb);
        $versionRepository = new VersionRepository($wpdb);

        // Create services
        $listingManager = new WordPressListingManager($minisiteRepository, $versionRepository);
        $listingService = new MinisiteListingService($listingManager);

        // Create handlers
        $listMinisitesHandler = new ListMinisitesHandler($listingService);

        // Create additional dependencies for refactored controllers
        $requestHandler = new \Minisite\Features\MinisiteListing\Http\ListingRequestHandler();
        $responseHandler = new \Minisite\Features\MinisiteListing\Http\ListingResponseHandler();
        $renderer = new \Minisite\Features\MinisiteListing\Rendering\ListingRenderer();

        // Create controllers
        $listingController = new ListingController(
            $listMinisitesHandler,
            $listingService,
            $requestHandler,
            $responseHandler,
            $renderer
        );

        // Create and return hooks
        return new ListingHooks($listingController);
    }
}
