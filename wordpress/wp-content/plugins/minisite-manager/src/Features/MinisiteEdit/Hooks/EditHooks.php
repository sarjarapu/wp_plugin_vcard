<?php

namespace Minisite\Features\MinisiteEdit\Hooks;

use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;

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
    public function __construct(
        private EditController $editController,
        private WordPressEditManager $wordPressManager
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
        // Check if this is an account route handled by our new system
        if ((int) $this->wordPressManager->getQueryVar('minisite_account') !== 1) {
            return;
        }

        $action = $this->wordPressManager->getQueryVar('minisite_account_action');

        // Handle edit and preview routes
        if ($action === 'edit') {
            // Route to edit controller
            $this->editController->handleEdit();
            exit;
        } elseif ($action === 'preview') {
            // Route to preview controller
            $this->editController->handlePreview();
            exit;
        }
    }
}
