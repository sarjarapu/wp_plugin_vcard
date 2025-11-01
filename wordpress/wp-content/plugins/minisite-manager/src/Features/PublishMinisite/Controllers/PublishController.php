<?php

namespace Minisite\Features\PublishMinisite\Controllers;

use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Rendering\PublishRenderer;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Security\FormSecurityHelper;
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
        private FormSecurityHelper $formSecurityHelper
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('publish-controller');
    }

    /**
     * Handle publish request (GET)
     */
    public function handlePublish(): void
    {
        // Check authentication
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/login'));
        }

        // Get site ID from query variable (works for both rewrite rules and query parameters)
        $siteId = $this->wordPressManager->getQueryVar('minisite_id');

        if (!$siteId) {
            $this->logger->warning('Publish page accessed without site_id', [
                'query_vars' => $_GET ?? [],
            ]);
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        }

        try {
            $publishData = $this->publishService->getMinisiteForPublishing($siteId);
            $this->publishRenderer->renderPublishPage($publishData);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load publish page', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
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
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);
            return;
        }

        if (!$this->formSecurityHelper->verifyNonce('check_slug_availability', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);
            return;
        }

        $businessSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('business_slug')
        );
        $locationSlug = $this->wordPressManager->sanitizeTextField(
            $this->wordPressManager->getPostData('location_slug')
        );

        // TODO: Implement availability checking via SlugAvailabilityService
        $this->wordPressManager->sendJsonError('Not implemented yet', 501);
    }

    /**
     * Handle AJAX: Reserve slug
     */
    public function handleReserveSlug(): void
    {
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);
            return;
        }

        if (!$this->formSecurityHelper->verifyNonce('reserve_slug', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);
            return;
        }

        // TODO: Implement reservation via ReservationService
        $this->wordPressManager->sendJsonError('Not implemented yet', 501);
    }

    /**
     * Handle AJAX: Cancel reservation
     */
    public function handleCancelReservation(): void
    {
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);
            return;
        }

        if (!$this->formSecurityHelper->verifyNonce('cancel_reservation', 'nonce')) {
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
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->sendJsonError('Not authenticated', 401);
            return;
        }

        if (!$this->formSecurityHelper->verifyNonce('create_minisite_order', 'nonce')) {
            $this->wordPressManager->sendJsonError('Security check failed', 403);
            return;
        }

        // TODO: Implement WooCommerce order creation
        $this->wordPressManager->sendJsonError('Not implemented yet', 501);
    }
}

