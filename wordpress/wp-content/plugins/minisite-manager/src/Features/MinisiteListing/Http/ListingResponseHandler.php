<?php

namespace Minisite\Features\MinisiteListing\Http;

use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;

/**
 * Listing Response Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP responses and redirects for listing functionality
 * - Manages redirects
 * - Sets HTTP headers
 * - Handles error responses
 */
final class ListingResponseHandler
{
    public function __construct(
        private WordPressListingManager $wordPressManager
    ) {
    }

    /**
     * Redirect to login page
     */
    public function redirectToLogin(?string $redirectTo = null): void
    {
        $loginUrl = $this->wordPressManager->getHomeUrl('/account/login');
        if ($redirectTo) {
            $loginUrl .= '?redirect_to=' . urlencode($redirectTo);
        }

        $this->wordPressManager->redirect($loginUrl);
        exit;
    }

    /**
     * Redirect to sites listing page
     */
    public function redirectToSites(): void
    {
        $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        exit;
    }

    /**
     * Generic redirect
     */
    public function redirect(string $url): void
    {
        $this->wordPressManager->redirect($url);
        exit;
    }
}
