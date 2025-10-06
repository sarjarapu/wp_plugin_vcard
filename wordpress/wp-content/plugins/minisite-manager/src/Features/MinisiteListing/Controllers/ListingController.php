<?php

namespace Minisite\Features\MinisiteListing\Controllers;

use Minisite\Features\MinisiteListing\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\Http\ListingRequestHandler;
use Minisite\Features\MinisiteListing\Http\ListingResponseHandler;
use Minisite\Features\MinisiteListing\Rendering\ListingRenderer;

/**
 * Listing Controller
 *
 * SINGLE RESPONSIBILITY: Coordinate minisite listing flow
 * - Delegates HTTP handling to ListingRequestHandler
 * - Delegates business logic to Handlers
 * - Delegates responses to ListingResponseHandler
 * - Delegates rendering to ListingRenderer
 *
 * This controller only orchestrates the listing flow - it doesn't do the work itself!
 */
final class ListingController
{
    public function __construct(
        private ListMinisitesHandler $listMinisitesHandler,
        private MinisiteListingService $listingService,
        private ListingRequestHandler $requestHandler,
        private ListingResponseHandler $responseHandler,
        private ListingRenderer $renderer
    ) {
    }

    /**
     * Handle listing minisites
     */
    public function handleList(): void
    {
        if (!is_user_logged_in()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ?
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectUrl);
            return;
        }

        try {
            $command = $this->requestHandler->parseListMinisitesRequest();
            if (!$command) {
                $this->responseHandler->redirectToLogin();
                return;
            }
            $result = $this->listMinisitesHandler->handle($command);
            
            if (!$result['success']) {
                $this->renderListPage($result['error'] ?? 'Failed to load minisites');
                return;
            }
            $this->renderListPage(null, $result['minisites'] ?? []);
        } catch (\Exception $e) {
            $this->renderListPage('An error occurred while loading minisites');
        }
    }

    /**
     * Render list page
     */
    private function renderListPage(?string $error = null, array $minisites = []): void
    {
        $currentUser = wp_get_current_user();
        
        $data = [
            'page_title' => 'My Minisites',
            'sites' => $minisites,  // Template expects 'sites', not 'minisites'
            'error' => $error,
            'can_create' => current_user_can('minisite_create'),
            'user' => $currentUser,
        ];

        $this->renderer->renderListPage($data);
    }
}