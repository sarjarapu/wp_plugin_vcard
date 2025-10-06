<?php

namespace Minisite\Features\MinisiteEditor\Http;

/**
 * Editor Response Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP responses and redirects
 * - Manages redirects
 * - Sets HTTP headers
 * - Handles error responses
 */
final class EditorResponseHandler
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
     * Redirect to sites page
     */
    public function redirectToSites(): void
    {
        wp_redirect(home_url('/account/sites'));
        exit;
    }

    /**
     * Redirect to specific URL
     */
    public function redirect(string $url): void
    {
        wp_redirect($url);
        exit;
    }

    /**
     * Set 404 response
     */
    public function set404Response(): void
    {
        status_header(404);
        nocache_headers();
        
        global $wp_query;
        $wp_query->set_404();
    }

    /**
     * Set 500 response
     */
    public function set500Response(): void
    {
        status_header(500);
        nocache_headers();
    }
}
