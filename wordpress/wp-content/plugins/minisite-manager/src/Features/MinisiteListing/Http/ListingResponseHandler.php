<?php

namespace Minisite\Features\MinisiteListing\Http;

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
    /**
     * Redirect to login page
     */
    public function redirectToLogin(?string $redirectTo = null): void
    {
        $loginUrl = home_url('/account/login');
        if ($redirectTo) {
            $loginUrl .= '?redirect_to=' . urlencode($redirectTo);
        }
        
        wp_redirect($loginUrl);
        exit;
    }

    /**
     * Redirect to sites listing page
     */
    public function redirectToSites(): void
    {
        wp_redirect(home_url('/account/sites'));
        exit;
    }

    /**
     * Generic redirect
     */
    public function redirect(string $url): void
    {
        wp_redirect($url);
        exit;
    }
}