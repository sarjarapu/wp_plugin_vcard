<?php

namespace Minisite\Features\MinisiteEdit\Hooks;

use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;

/**
 * Edit Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for edit routes
 * - Registers rewrite rules for edit pages
 * - Hooks into WordPress template_redirect
 * - Manages edit route handling
 */
class EditHooks
{
    /**
     * Flag value set by rewrite rules to indicate account management routes
     */
    private const ACCOUNT_ROUTE_FLAG = '1';

    public function __construct(
        private EditController $editController,
        private WordPressEditManager $wordPressManager,
        private MinisitePageController $minisiteViewerController
    ) {
    }

    /**
     * Register all edit hooks
     */
    public function register(): void
    {
        // Note: template_redirect is registered by MinisiteEditFeature
    }

    /**
     * Handle edit and preview routes
     * This hooks into the existing minisite_account system
     */
    public function handleEditRoutes(): void
    {
        // Only handle account management routes (edit, preview, versions, etc.)
        // The rewrite rules set minisite_account=1 for account routes
        $isAccountRoute = $this->wordPressManager->getQueryVar('minisite_account') === self::ACCOUNT_ROUTE_FLAG;
        if (!$isAccountRoute) {
            return; // Not an account route, let other handlers process it
        }

        $action = $this->wordPressManager->getQueryVar('minisite_account_action');

        // Handle edit and preview routes
        if ($action === 'edit') {
            $this->editController->handleEdit();
            exit;
        } elseif ($action === 'preview') {
            // Delegate version-specific preview to MinisiteViewer
            $this->minisiteViewerController->handleVersionSpecificPreview();
            exit;
        }
    }
}
