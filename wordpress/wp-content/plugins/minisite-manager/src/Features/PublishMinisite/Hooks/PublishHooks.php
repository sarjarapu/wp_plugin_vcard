<?php

namespace Minisite\Features\PublishMinisite\Hooks;

use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;

/**
 * Publish Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for publish routes
 * - Registers rewrite rules for publish pages
 * - Hooks into WordPress template_redirect
 * - Manages publish route handling
 */
class PublishHooks
{
    /**
     * Flag value set by rewrite rules to indicate account management routes
     */
    private const ACCOUNT_ROUTE_FLAG = '1';

    public function __construct(
        private PublishController $publishController,
        private WordPressPublishManager $wordPressManager
    ) {
    }

    /**
     * Register all publish hooks
     */
    public function register(): void
    {
        // AJAX handlers for slug operations
        add_action('wp_ajax_check_slug_availability', [$this->publishController, 'handleCheckSlugAvailability']);
        add_action('wp_ajax_reserve_slug', [$this->publishController, 'handleReserveSlug']);
        add_action('wp_ajax_cancel_reservation', [$this->publishController, 'handleCancelReservation']);
        add_action('wp_ajax_create_minisite_order', [$this->publishController, 'handleCreateWooCommerceOrder']);

        // Note: template_redirect is registered by PublishMinisiteFeature
    }

    /**
     * Handle publish routes
     * This hooks into the existing minisite_account system
     */
    public function handlePublishRoutes(): void
    {
        // Only handle account management routes (publish)
        // The rewrite rules set minisite_account=1 for account routes
        $isAccountRoute = $this->wordPressManager->getQueryVar('minisite_account') === self::ACCOUNT_ROUTE_FLAG;
        if (!$isAccountRoute) {
            return; // Not an account route, let other handlers process it
        }

        $action = $this->wordPressManager->getQueryVar('minisite_account_action');

        // Handle publish route
        if ($action === 'publish') {
            $this->publishController->handlePublish();
            exit;
        }
    }

    /**
     * Get controller (for potential future use)
     */
    public function getController(): PublishController
    {
        return $this->publishController;
    }
}

