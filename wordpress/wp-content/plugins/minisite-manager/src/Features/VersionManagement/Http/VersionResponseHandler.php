<?php

namespace Minisite\Features\VersionManagement\Http;

/**
 * Handles HTTP responses for version management
 */
class VersionResponseHandler
{
    /**
     * Send JSON success response
     */
    public function sendJsonSuccess(array $data = [], int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public function sendJsonError(string $message, int $statusCode = 400): void
    {
        wp_send_json_error($message, $statusCode);
    }

    /**
     * Redirect to login page
     */
    public function redirectToLogin(string $redirectTo = ''): void
    {
        $redirectUrl = home_url('/account/login');
        if ($redirectTo) {
            $redirectUrl .= '?redirect_to=' . urlencode($redirectTo);
        }
        wp_redirect($redirectUrl);
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
     * Set 404 response
     */
    public function set404Response(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}
