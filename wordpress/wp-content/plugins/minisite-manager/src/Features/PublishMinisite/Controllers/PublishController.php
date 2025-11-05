<?php

namespace Minisite\Features\PublishMinisite\Controllers;

use Minisite\Features\PublishMinisite\Rendering\PublishRenderer;
use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\Services\SubscriptionActivationService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Psr\Log\LoggerInterface;

/**
 * Publish Controller
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for minisite publishing
 * - Manages publish page display
 * - Coordinates between services and renderers
 * - Handles authentication and authorization
 * - Handles AJAX requests for slug operations
 */
class PublishController
{
    private LoggerInterface $logger;

    public function __construct(
        private PublishService $publishService,
        private PublishRenderer $publishRenderer,
        private WordPressPublishManager $wordPressManager,
        private FormSecurityHelper $formSecurityHelper,
        private SubscriptionActivationService $subscriptionActivationService,
        private ReservationService $reservationService
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('publish-controller');
    }

    /**
     * Handle publish request (GET)
     */
    public function handlePublish(): void
    {
        // Check authentication
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/login'));
        }

        // Get site ID from query variable (works for both rewrite rules and query parameters)
        $siteId = $this->wordPressManager->getQueryVar('minisite_id');

        if (! $siteId) {
            $this->logger->warning('Publish page accessed without site_id', array(
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Logging only, not processing form data
                'query_vars' => $_GET,
            ));
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        }

        try {
            $publishData = $this->publishService->getMinisiteForPublishing($siteId);
            $this->publishRenderer->renderPublishPage($publishData);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load publish page', array(
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ));
            $this->wordPressManager->redirect(
                $this->wordPressManager->getHomeUrl('/account/sites?error=' . urlencode($e->getMessage()))
            );
        }
    }

    /**
     * Handle AJAX: Check slug availability
     */
    public function handleCheckSlugAvailability(): void
    {
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);

            return;
        }

        if (! $this->formSecurityHelper->verifyNonce('check_slug_availability', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);

            return;
        }

        $businessSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('business_slug')
        );
        $locationSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('location_slug')
        );

        try {
            $result = $this->publishService->getSlugAvailabilityService()->checkAvailability(
                $businessSlug,
                $locationSlug
            );

            $this->wordPressManager->sendJsonSuccess(array(
                'available' => $result->available,
                'message' => $result->message,
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to check slug availability', array(
                'business_slug' => $businessSlug,
                'location_slug' => $locationSlug,
                'error' => $e->getMessage(),
            ));
            $this->wordPressManager->sendJsonError('Failed to check slug availability: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle AJAX: Reserve slug
     */
    public function handleReserveSlug(): void
    {
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);

            return;
        }

        if (! $this->formSecurityHelper->verifyNonce('reserve_slug', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);

            return;
        }

        $businessSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('business_slug')
        );
        $locationSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('location_slug')
        );

        // Validate slug format
        if (empty($businessSlug) || ! preg_match('/^[a-z0-9-]+$/', $businessSlug)) {
            $this->wordPressManager->sendJsonError(
                'Business slug is required and can only contain lowercase letters, numbers, and hyphens',
                400
            );

            return;
        }

        if (! empty($locationSlug) && ! preg_match('/^[a-z0-9-]+$/', $locationSlug)) {
            $this->wordPressManager->sendJsonError(
                'Location slug can only contain lowercase letters, numbers, and hyphens',
                400
            );

            return;
        }

        try {
            $userId = $this->wordPressManager->getCurrentUserId();
            $result = $this->reservationService->reserveSlug($businessSlug, $locationSlug, $userId);

            $this->wordPressManager->sendJsonSuccess(array(
                'reservation_id' => $result->reservation_id,
                'expires_at' => $result->expires_at,
                'expires_in_seconds' => $result->expires_in_seconds,
                'message' => $result->message,
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to reserve slug', array(
                'business_slug' => $businessSlug,
                'location_slug' => $locationSlug,
                'error' => $e->getMessage(),
            ));
            $this->wordPressManager->sendJsonError($e->getMessage(), 409);
        }
    }

    /**
     * Handle AJAX: Cancel reservation
     */
    public function handleCancelReservation(): void
    {
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);

            return;
        }

        if (! $this->formSecurityHelper->verifyNonce('cancel_reservation', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);

            return;
        }

        // TODO: Implement cancellation via ReservationService
        $this->wordPressManager->sendJsonError('Not implemented yet', 501);
    }

    /**
     * Handle AJAX: Create WooCommerce order
     */
    public function handleCreateWooCommerceOrder(): void
    {
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);

            return;
        }

        if (! $this->formSecurityHelper->verifyNonce('create_minisite_order', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);

            return;
        }

        $minisiteId = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('minisite_id')
        );
        $businessSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('business_slug')
        );
        $locationSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('location_slug')
        );
        $reservationId = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('reservation_id')
        );

        if (empty($minisiteId) || empty($businessSlug) || empty($reservationId)) {
            $this->wordPressManager->sendJsonError('Missing required fields', 400);

            return;
        }

        try {
            // Check if WooCommerce is active
            if (! class_exists('WooCommerce')) {
                $this->wordPressManager->sendJsonError('WooCommerce is not active', 500);

                return;
            }

            global $wpdb;

            // Check if minisite already has an active subscription
            $paymentsTable = $wpdb->prefix . 'minisite_payments';
            $existingPayment = db::get_row(
                "SELECT * FROM {$paymentsTable} 
                 WHERE minisite_id = %s 
                 AND status IN ('active', 'grace_period') 
                 AND expires_at > NOW()",
                array($minisiteId)
            );

            if ($existingPayment) {
                // Minisite already has active subscription, just publish it
                $locationSlugForPublish = empty($locationSlug) ? '' : $locationSlug;
                $this->subscriptionActivationService->publishDirectly(
                    $minisiteId,
                    $businessSlug,
                    $locationSlugForPublish,
                    $reservationId
                );

                $this->wordPressManager->sendJsonSuccess(array(
                    'message' => 'Minisite published successfully! You already have an active subscription.',
                    'redirect_url' => $this->wordPressManager->getHomeUrl('/account/sites'),
                ));

                return;
            }

            // Find the minisite subscription product by SKU
            $productId = wc_get_product_id_by_sku('NMS001');
            if (! $productId) {
                $this->wordPressManager->sendJsonError('Minisite subscription product (SKU: NMS001) not found', 500);

                return;
            }

            // Clear any existing cart items to avoid confusion
            WC()->cart->empty_cart();

            // Add the subscription product to cart
            $cartItemKey = WC()->cart->add_to_cart($productId, 1);

            if (! $cartItemKey) {
                $this->wordPressManager->sendJsonError('Failed to add product to cart', 500);

                return;
            }

            // Build slug string
            $slugString = $businessSlug;
            if (! empty($locationSlug)) {
                $slugString .= '/' . $locationSlug;
            }

            // Add minisite-specific meta data to cart item
            WC()->cart->cart_contents[$cartItemKey]['minisite_id'] = $minisiteId;
            WC()->cart->cart_contents[$cartItemKey]['minisite_slug'] = $slugString;
            WC()->cart->cart_contents[$cartItemKey]['minisite_reservation_id'] = $reservationId;

            // Also store in session for later retrieval
            WC()->session->set(
                'minisite_cart_data',
                array(
                    'minisite_id' => $minisiteId,
                    'minisite_slug' => $slugString,
                    'minisite_reservation_id' => $reservationId,
                )
            );

            // Save cart
            WC()->cart->set_session();

            // Get cart URL
            $cartUrl = wc_get_cart_url();

            $this->logger->info('Added minisite subscription to cart', array(
                'minisite_id' => $minisiteId,
                'cart_url' => $cartUrl,
            ));

            $this->wordPressManager->sendJsonSuccess(array(
                'cart_url' => $cartUrl,
                'message' => 'Product added to cart successfully. Redirecting to cart...',
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to create WooCommerce order', array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));
            $this->wordPressManager->sendJsonError('Failed to create order: ' . $e->getMessage(), 500);
        }
    }
}
