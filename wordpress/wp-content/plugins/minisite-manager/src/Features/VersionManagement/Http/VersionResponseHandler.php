<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;

/**
 * Handles HTTP responses for version management
 */
class VersionResponseHandler
{
    public function __construct(
        private WordPressVersionManager $wordPressManager
    ) {
    }

    /**
     * Send JSON success response
     */
    public function sendJsonSuccess(array $data = [], int $statusCode = 200): void
    {
        $this->wordPressManager->sendJsonSuccess($data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public function sendJsonError(string $message, int $statusCode = 400): void
    {
        $this->wordPressManager->sendJsonError($message, $statusCode);
    }

    /**
     * Redirect to login page
     */
    public function redirectToLogin(string $redirectTo = ''): void
    {
        $redirectUrl = $this->wordPressManager->getHomeUrl('/account/login');
        if ($redirectTo) {
            $redirectUrl .= '?redirect_to=' . urlencode($redirectTo);
        }
        $this->wordPressManager->redirect($redirectUrl);
        exit;
    }

    /**
     * Redirect to sites page
     */
    public function redirectToSites(): void
    {
        $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        exit;
    }

    /**
     * Set 404 response
     */
    public function set404Response(): void
    {
        global $wp_query;
        $wp_query->set_404();
        $this->wordPressManager->setStatusHeader(404);
        $this->wordPressManager->setNoCacheHeaders();
    }
}
