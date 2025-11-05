<?php

namespace Minisite\Features\MinisiteViewer\Hooks;

use Minisite\Features\BaseFeature\Hooks\BaseHook;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * View Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for minisite view routes
 * - Registers rewrite rules for minisite pages
 * - Hooks into WordPress template_redirect
 * - Manages minisite view route handling
 */
final class ViewHooks extends BaseHook
{
    public function __construct(
        private MinisitePageController $minisitePageController,
        private WordPressMinisiteManager $wordPressManager,
        TerminationHandlerInterface $terminationHandler
    ) {
        parent::__construct($terminationHandler);
    }

    /**
     * Register all view hooks
     */
    public function register(): void
    {
        add_action('init', array($this, 'addRewriteRules'));
        // Use priority 5 to run before the main plugin's template_redirect (which runs at priority 10)
        add_action('template_redirect', array($this, 'handleViewRoutes'), 5);
    }

    /**
     * Add rewrite rules for minisite pages
     * Note: We don't add rewrite rules here because the existing RewriteRegistrar
     * already handles /b/{business}/{location} routes. We just need to hook into the existing system.
     */
    public function addRewriteRules(): void
    {
        // Add query vars for our new display system
        add_filter('query_vars', array($this, 'addQueryVars'));
    }

    /**
     * Add query variables for view routes
     * Note: We use the existing minisite_biz and minisite_loc vars
     */
    public function addQueryVars(array $vars): array
    {
        // We don't need to add any new query vars since we're using the existing system
        return $vars;
    }

    /**
     * Handle view routes
     * This hooks into the existing /b/{business}/{location} system
     */
    public function handleViewRoutes(): void
    {
        // Check if this is a minisite view route
        $businessSlug = $this->wordPressManager->getQueryVar('minisite_biz');
        $locationSlug = $this->wordPressManager->getQueryVar('minisite_loc');

        if (! $businessSlug || ! $locationSlug) {
            return;
        }

        // Route to controller
        $this->minisitePageController->handleView();

        // Terminate after handling route (inherited from BaseHook)
        $this->terminate();
    }

    /**
     * Get the minisite page controller
     * This allows other features to access the controller for delegation
     */
    public function getController(): MinisitePageController
    {
        return $this->minisitePageController;
    }
}
