<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * WooCommerce Integration Service
 *
 * SINGLE RESPONSIBILITY: Handle WooCommerce integration for minisite purchases
 * - Transfer cart data to order metadata
 * - Activate subscription on order completion
 * - Handle existing subscriber direct publish
 */
class WooCommerceIntegration
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressPublishManager $wordPressManager,
        private SubscriptionActivationService $subscriptionActivationService
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('woocommerce-integration');
    }

    /**
     * Transfer minisite data from session to order metadata
     */
    public function transferCartDataToOrder($order, $data): void
    {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        $cartData = WC()->session->get('minisite_cart_data');
        if (! $cartData) {
            return;
        }

        $order->update_meta_data('_minisite_id', $cartData['minisite_id'] ?? '');
        $order->update_meta_data('_slug', $cartData['minisite_slug'] ?? '');
        $order->update_meta_data('_reservation_id', $cartData['minisite_reservation_id'] ?? '');

        $this->logger->info('Transferred minisite cart data to order', array(
            'order_id' => $order->get_id(),
            'minisite_id' => $cartData['minisite_id'] ?? null,
        ));
    }

    /**
     * Transfer cart item metadata to order item metadata
     */
    public function transferCartItemToOrderItem($item, $cart_item_key, $values, $order): void
    {
        if (! isset($values['minisite_id'])) {
            return;
        }

        $item->add_meta_data('_minisite_id', $values['minisite_id']);
        $item->add_meta_data('_minisite_slug', $values['minisite_slug'] ?? '');
        $item->add_meta_data('_minisite_reservation_id', $values['minisite_reservation_id'] ?? '');

        // Also transfer to order metadata for easier access
        $order->update_meta_data('_minisite_id', $values['minisite_id']);
        $order->update_meta_data('_slug', $values['minisite_slug'] ?? '');
        $order->update_meta_data('_reservation_id', $values['minisite_reservation_id'] ?? '');

        $this->logger->info('Transferred minisite cart item data to order item', array(
            'order_id' => $order->get_id(),
            'minisite_id' => $values['minisite_id'] ?? null,
        ));
    }

    /**
     * Activate minisite subscription when order is completed
     */
    public function activateSubscriptionOnOrderCompletion(int $orderId): void
    {
        try {
            $this->logger->info('Processing order completion for minisite subscription', array(
                'order_id' => $orderId,
            ));

            $this->subscriptionActivationService->activateFromOrder($orderId);

            // Clear session data after successful activation
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('minisite_cart_data', null);
            }
        } catch (\Exception $e) {
            // Log error but don't break the order completion process
            $this->logger->error('Failed to activate minisite subscription for order', array(
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ));
        }
    }
}
