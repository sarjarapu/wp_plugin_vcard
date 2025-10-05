<?php

namespace Minisite\Features\VersionManagement\Hooks;

use Minisite\Features\VersionManagement\Controllers\VersionController;

/**
 * WordPress hooks for version management
 */
class VersionHooks
{
    public function __construct(
        private VersionController $versionController
    ) {
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void
    {
        // Register AJAX actions for version management
        add_action('wp_ajax_minisite_create_draft', [$this, 'handleCreateDraft']);
        add_action('wp_ajax_minisite_publish_version', [$this, 'handlePublishVersion']);
        add_action('wp_ajax_minisite_rollback_version', [$this, 'handleRollbackVersion']);

        // Register template redirect for version history page
        add_action('template_redirect', [$this, 'handleVersionHistoryPage']);
    }

    /**
     * Handle version history page display
     */
    public function handleVersionHistoryPage(): void
    {
        // Check if this is a version management route
        if ((int) get_query_var('minisite_account') === 1 && 
            get_query_var('minisite_account_action') === 'versions') {
            error_log('VersionManagement: Route matched, handling version history page');
            $this->versionController->handleListVersions();
            exit;
        }
    }

    /**
     * Handle list versions AJAX request
     */
    public function handleListVersions(): void
    {
        $this->versionController->handleListVersions();
    }

    /**
     * Handle create draft AJAX request
     */
    public function handleCreateDraft(): void
    {
        $this->versionController->handleCreateDraft();
    }

    /**
     * Handle publish version AJAX request
     */
    public function handlePublishVersion(): void
    {
        $this->versionController->handlePublishVersion();
    }

    /**
     * Handle rollback version AJAX request
     */
    public function handleRollbackVersion(): void
    {
        $this->versionController->handleRollbackVersion();
    }
}
