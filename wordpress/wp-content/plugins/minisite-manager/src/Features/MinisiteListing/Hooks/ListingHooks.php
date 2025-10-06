<?php

namespace Minisite\Features\MinisiteListing\Hooks;

use Minisite\Features\MinisiteListing\Controllers\ListingController;

/**
 * Listing Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for minisite listing routes
 * - Registers rewrite rules for listing pages
 * - Hooks into WordPress template_redirect
 * - Manages listing route handling
 */
final class ListingHooks
{
    public function __construct(
        private ListingController $listingController
    ) {
    }

    /**
     * Register all listing hooks
     */
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        // Use priority 5 to run before the main plugin's template_redirect (which runs at priority 10)
        add_action('template_redirect', [$this, 'handleListingRoutes'], 5);
    }

    /**
     * Add rewrite rules for listing pages
     * Note: We don't add rewrite rules here because the existing RewriteRegistrar
     * already handles /account/* routes. We just need to hook into the existing system.
     */
    public function addRewriteRules(): void
    {
        // Add query vars for our new listing system
        add_filter('query_vars', [$this, 'addQueryVars']);
    }

    /**
     * Add query variables for listing routes
     * Note: We use the existing minisite_account and minisite_account_action vars
     */
    public function addQueryVars(array $vars): array
    {
        // We don't need to add any new query vars since we're using the existing system
        return $vars;
    }

    /**
     * Handle listing routes
     */
    public function handleListingRoutes(): void
    {
        // Check if this is a listing route
        if ((int) get_query_var('minisite_account') === 1) {
            $action = get_query_var('minisite_account_action');
            
            switch ($action) {
                case 'sites':
                    $this->listingController->handleList();
                    exit;
            }
        }
    }
}
