<?php

namespace Minisite\Features\MinisiteEdit\Hooks;

use Minisite\Features\BaseFeature\Hooks\BaseHook;
use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Edit Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for edit routes
 * - Registers rewrite rules for edit pages
 * - Hooks into WordPress template_redirect
 * - Manages edit route handling
 */
class EditHooks extends BaseHook
{
    /**
     * Flag value set by rewrite rules to indicate account management routes
     */
    private const ACCOUNT_ROUTE_FLAG = '1';

    public function __construct(
        private EditController $editController,
        private WordPressEditManager $wordPressManager,
        private MinisitePageController $minisiteViewerController,
        TerminationHandlerInterface $terminationHandler
    ) {
        parent::__construct($terminationHandler);
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
        // Handle both string '1' and integer 1 (WordPress query vars can be either)
        $accountValue = $this->wordPressManager->getQueryVar('minisite_account');
        $isAccountRoute = ($accountValue === self::ACCOUNT_ROUTE_FLAG || $accountValue === 1);
        if (!$isAccountRoute) {
            return; // Not an account route, let other handlers process it
        }

        $action = $this->wordPressManager->getQueryVar('minisite_account_action');

        // Handle edit and preview routes
        if ($action === 'edit') {
            $this->editController->handleEdit();
            // Terminate after handling route (inherited from BaseHook)
            $this->terminate();
        } elseif ($action === 'preview') {
            // Delegate version-specific preview to MinisiteViewer
            $this->minisiteViewerController->handleVersionSpecificPreview();
            // Terminate after handling route (inherited from BaseHook)
            $this->terminate();
        }
    }
}
