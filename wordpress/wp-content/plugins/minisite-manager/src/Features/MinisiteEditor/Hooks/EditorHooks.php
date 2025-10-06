<?php

namespace Minisite\Features\MinisiteEditor\Hooks;

use Minisite\Features\MinisiteEditor\Controllers\SitesController;
use Minisite\Features\MinisiteEditor\Controllers\NewMinisiteController;

/**
 * Editor Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for minisite editor routes
 * - Registers rewrite rules for editor pages
 * - Hooks into WordPress template_redirect
 * - Manages editor route handling
 */
final class EditorHooks
{
    public function __construct(
        private SitesController $sitesController,
        private NewMinisiteController $newMinisiteController
    ) {
    }

    /**
     * Register all editor hooks
     */
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        // Use priority 5 to run before the main plugin's template_redirect (which runs at priority 10)
        add_action('template_redirect', [$this, 'handleEditorRoutes'], 5);
    }

    /**
     * Add rewrite rules for editor pages
     * Note: We don't add rewrite rules here because the existing RewriteRegistrar
     * already handles /account/* routes. We just need to hook into the existing system.
     */
    public function addRewriteRules(): void
    {
        // Add query vars for our new editor system
        add_filter('query_vars', [$this, 'addQueryVars']);
    }

    /**
     * Add query variables for editor routes
     * Note: We use the existing minisite_account and minisite_account_action vars
     */
    public function addQueryVars(array $vars): array
    {
        // We don't need to add any new query vars since we're using the existing system
        return $vars;
    }

    /**
     * Handle editor routes
     */
    public function handleEditorRoutes(): void
    {
        // Check if this is an editor route
        if ((int) get_query_var('minisite_account') === 1) {
            $action = get_query_var('minisite_account_action');
            
            switch ($action) {
                case 'sites':
                    $this->sitesController->handleList();
                    exit;
                    
                case 'new':
                    $this->newMinisiteController->handleNew();
                    exit;
                    
                case 'edit':
                    $this->sitesController->handleEdit();
                    exit;
                    
                case 'preview':
                    $this->sitesController->handlePreview();
                    exit;
            }
        }
    }
}
