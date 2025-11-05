<?php

namespace Minisite\Features\NewMinisite\Hooks;

use Minisite\Features\BaseFeature\Hooks\BaseHook;
use Minisite\Features\NewMinisite\Controllers\NewMinisiteController;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * New Minisite Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for new minisite routes
 * - Registers rewrite rules for new minisite pages
 * - Hooks into WordPress template_redirect
 * - Manages new minisite route handling
 */
class NewMinisiteHooks extends BaseHook
{
    /**
     * Flag value set by rewrite rules to indicate account management routes
     */
    private const ACCOUNT_ROUTE_FLAG = '1';

    public function __construct(
        private NewMinisiteController $newMinisiteController,
        private WordPressNewMinisiteManager $wordPressManager,
        TerminationHandlerInterface $terminationHandler
    ) {
        parent::__construct($terminationHandler);
    }

    /**
     * Register all new minisite hooks
     */
    public function register(): void
    {
        // Note: template_redirect is registered by NewMinisiteFeature
    }

    /**
     * Handle new minisite routes
     * This hooks into the existing minisite_account system
     */
    public function handleNewMinisiteRoutes(): void
    {
        // Only handle account management routes (new minisite creation)
        // The rewrite rules set minisite_account=1 for account routes
        $isAccountRoute = $this->wordPressManager->getQueryVar('minisite_account') === self::ACCOUNT_ROUTE_FLAG;
        if (! $isAccountRoute) {
            return; // Not an account route, let other handlers process it
        }

        $action = $this->wordPressManager->getQueryVar('minisite_account_action');

        // Handle new minisite creation route
        if ($action === 'new') {
            $this->newMinisiteController->handleNewMinisite();
            // Terminate after handling route (inherited from BaseHook)
            $this->terminate();
        }
    }
}
