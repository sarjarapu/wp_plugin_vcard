<?php

namespace Minisite\Features\PublishMinisite\Hooks;

use Minisite\Features\BaseFeature\Hooks\BaseHook;
use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\Services\WooCommerceIntegration;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Publish Hooks
 *
 * SINGLE RESPONSIBILITY: Register WordPress hooks for publish routes
 * - Registers rewrite rules for publish pages
 * - Hooks into WordPress template_redirect
 * - Manages publish route handling
 */
class PublishHooks extends BaseHook
{
    /**
     * Flag value set by rewrite rules to indicate account management routes
     */
    private const ACCOUNT_ROUTE_FLAG = '1';

    public function __construct(
        private PublishController $publishController,
        private WordPressPublishManager $wordPressManager,
        private WooCommerceIntegration $wooCommerceIntegration,
        TerminationHandlerInterface $terminationHandler
    ) {
        parent::__construct($terminationHandler);
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

        // WooCommerce integration hooks
        if (class_exists('WooCommerce')) {
            add_action(
                'woocommerce_checkout_create_order',
                [$this->wooCommerceIntegration, 'transferCartDataToOrder'],
                10,
                2
            );
            add_action(
                'woocommerce_checkout_create_order_line_item',
                [$this->wooCommerceIntegration, 'transferCartItemToOrderItem'],
                10,
                4
            );
            add_action(
                'woocommerce_order_status_completed',
                [$this->wooCommerceIntegration, 'activateSubscriptionOnOrderCompletion'],
                10,
                1
            );
        }

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
            // Terminate after handling route (inherited from BaseHook)
            $this->terminate();
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
