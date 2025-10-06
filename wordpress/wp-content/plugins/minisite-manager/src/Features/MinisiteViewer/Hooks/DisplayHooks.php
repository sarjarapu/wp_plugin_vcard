<?php

namespace Minisite\Features\MinisiteViewer\Hooks;

use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;

/**
 * Display Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for minisite display routes
 * - Registers rewrite rules for minisite pages
 * - Hooks into WordPress template_redirect
 * - Manages minisite display route handling
 */
final class DisplayHooks
{
    public function __construct(
        private MinisitePageController $minisitePageController
    ) {
    }

    /**
     * Register all display hooks
     */
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        // Use priority 5 to run before the main plugin's template_redirect (which runs at priority 10)
        add_action('template_redirect', [$this, 'handleDisplayRoutes'], 5);
    }

    /**
     * Add rewrite rules for minisite pages
     * Note: We don't add rewrite rules here because the existing RewriteRegistrar
     * already handles /b/{business}/{location} routes. We just need to hook into the existing system.
     */
    public function addRewriteRules(): void
    {
        // Add query vars for our new display system
        add_filter('query_vars', [$this, 'addQueryVars']);
    }

    /**
     * Add query variables for display routes
     * Note: We use the existing minisite_biz and minisite_loc vars
     */
    public function addQueryVars(array $vars): array
    {
        // We don't need to add any new query vars since we're using the existing system
        return $vars;
    }

    /**
     * Handle display routes
     * This hooks into the existing /b/{business}/{location} system
     */
    public function handleDisplayRoutes(): void
    {
        // Check if this is a minisite display route
        $businessSlug = get_query_var('minisite_biz');
        $locationSlug = get_query_var('minisite_loc');

        if (!$businessSlug || !$locationSlug) {
            return;
        }

        // Route to controller
        $this->minisitePageController->handleDisplay();

        // Exit to prevent the old system from handling this request
        exit;
    }
}
