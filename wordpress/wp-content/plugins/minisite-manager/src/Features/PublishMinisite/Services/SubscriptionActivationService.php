<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Psr\Log\LoggerInterface;

/**
 * Subscription Activation Service
 *
 * SINGLE RESPONSIBILITY: Handle subscription activation and minisite publishing
 * - Activate subscription from WooCommerce order
 * - Update minisite slugs and publish status
 * - Create payment records
 * - Handle reservation cleanup
 */
class SubscriptionActivationService
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressPublishManager $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('subscription-activation-service');
    }

    /**
     * Activate subscription from WooCommerce order
     */
    public function activateFromOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \RuntimeException('Order not found');
        }

        // Get minisite data from order meta (fallback) or order items
        $minisiteId = $order->get_meta('_minisite_id');
        $slug = $order->get_meta('_slug');
        $reservationId = $order->get_meta('_reservation_id');

        // If not found in order meta, try to get from order items
        if (! $minisiteId) {
            foreach ($order->get_items() as $item) {
                $itemMinisiteId = $item->get_meta('_minisite_id');
                if ($itemMinisiteId) {
                    $minisiteId = $itemMinisiteId;
                    $slug = $item->get_meta('_minisite_slug') ?: $slug;
                    $reservationId = $item->get_meta('_minisite_reservation_id') ?: $reservationId;

                    break;
                }
            }
        }

        // If still not found, try session (fallback)
        if (! $minisiteId && function_exists('WC') && WC()->session) {
            $cartData = WC()->session->get('minisite_cart_data');
            if ($cartData) {
                $minisiteId = $cartData['minisite_id'] ?? '';
                $slug = $cartData['minisite_slug'] ?? '';
                $reservationId = $cartData['minisite_reservation_id'] ?? '';
            }
        }

        if (! $minisiteId) {
            throw new \RuntimeException('No minisite ID found in order or session');
        }

        // Parse slug
        $slugParts = explode('/', $slug);
        $businessSlug = $slugParts[0] ?? '';
        $locationSlug = $slugParts[1] ?? '';

        if (! $businessSlug) {
            throw new \RuntimeException('Invalid slug format');
        }

        global $wpdb;
        db::query('START TRANSACTION');

        try {
            // Get current expiration date (if any)
            $currentExpiration = db::get_var(
                "SELECT expires_at FROM {$wpdb->prefix}minisite_payments 
                 WHERE minisite_id = %s AND status = 'active' 
                 ORDER BY expires_at DESC LIMIT 1",
                array($minisiteId)
            );

            // Calculate new expiration date
            $baseDate = $currentExpiration ?: current_time('mysql');
            $newExpiration = PaymentConstants::calculateExpirationDate($baseDate);
            $gracePeriodEnds = PaymentConstants::calculateGracePeriodEnd($newExpiration);

            // Update minisite with permanent slugs and publish it
            $minisiteRepository = $this->wordPressManager->getMinisiteRepository();
            $minisiteRepository->updateSlugs($minisiteId, $businessSlug, $locationSlug);
            $minisiteRepository->publishMinisite($minisiteId);

            // Create payment record
            $paymentId = db::insert(
                $wpdb->prefix . 'minisite_payments',
                array(
                    'minisite_id' => $minisiteId,
                    'user_id' => $order->get_customer_id(),
                    'woocommerce_order_id' => $orderId,
                    'status' => 'active',
                    'amount' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'payment_method' => 'woocommerce',
                    'payment_reference' => $order->get_transaction_id() ?: 'order_' . $orderId,
                    'paid_at' => current_time('mysql'),
                    'expires_at' => $newExpiration,
                    'grace_period_ends_at' => $gracePeriodEnds,
                    'renewed_at' => null,
                    'reclaimed_at' => null,
                ),
                array('%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($paymentId === false) {
                throw new \RuntimeException('Failed to create payment record');
            }

            // Create payment history record
            $this->createPaymentHistoryRecord(
                $minisiteId,
                (int) db::get_insert_id(),
                'initial_payment',
                'order_' . $orderId,
                $order->get_total(),
                $order->get_currency()
            );

            // Clean up reservation
            if ($reservationId) {
                $reservationsTable = $wpdb->prefix . 'minisite_reservations';
                db::query(
                    "DELETE FROM {$reservationsTable} WHERE id = %s",
                    array($reservationId)
                );
            }

            db::query('COMMIT');

            $this->logger->info('Successfully activated minisite subscription', array(
                'order_id' => $orderId,
                'minisite_id' => $minisiteId,
                'payment_id' => db::get_insert_id(),
            ));
        } catch (\Exception $e) {
            db::query('ROLLBACK');
            $this->logger->error('Failed to activate subscription', array(
                'order_id' => $orderId,
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * Create payment history record
     */
    private function createPaymentHistoryRecord(
        string $minisiteId,
        int $paymentId,
        string $action,
        string $paymentReference,
        float $amount,
        string $currency
    ): void {
        global $wpdb;

        $expiresAt = PaymentConstants::calculateExpirationDate();
        $gracePeriodEndsAt = PaymentConstants::calculateGracePeriodEnd($expiresAt);

        db::insert(
            $wpdb->prefix . 'minisite_payment_history',
            array(
                'minisite_id' => $minisiteId,
                'payment_id' => $paymentId,
                'action' => $action,
                'amount' => $amount,
                'currency' => $currency,
                'payment_reference' => $paymentReference,
                'expires_at' => $expiresAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'new_owner_user_id' => null,
            ),
            array('%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d')
        );
    }

    /**
     * Publish minisite directly without payment (for existing subscribers)
     */
    public function publishDirectly(
        string $minisiteId,
        string $businessSlug,
        string $locationSlug,
        ?string $reservationId = null
    ): void {
        global $wpdb;

        db::query('START TRANSACTION');

        try {
            $minisiteRepository = $this->wordPressManager->getMinisiteRepository();
            $minisiteRepository->updateStatus($minisiteId, 'published');

            // Update slugs if they're different from current ones
            $currentMinisite = $minisiteRepository->findById($minisiteId);
            if ($currentMinisite) {
                $currentSlugs = $currentMinisite->slugs;
                if ($currentSlugs->business !== $businessSlug || $currentSlugs->location !== $locationSlug) {
                    $minisiteRepository->updateSlugs($minisiteId, $businessSlug, $locationSlug);
                }
            }

            // Clean up reservation
            if ($reservationId) {
                $reservationsTable = $wpdb->prefix . 'minisite_reservations';
                db::query(
                    "DELETE FROM {$reservationsTable} WHERE id = %s",
                    array($reservationId)
                );
            }

            db::query('COMMIT');

            $this->logger->info('Successfully published minisite directly (existing subscriber)', array(
                'minisite_id' => $minisiteId,
            ));
        } catch (\Exception $e) {
            db::query('ROLLBACK');
            $this->logger->error('Failed to publish minisite directly', array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));

            throw $e;
        }
    }
}
