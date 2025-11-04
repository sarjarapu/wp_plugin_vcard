<?php

namespace Minisite\Features\Authentication\Http;

use Minisite\Features\Authentication\WordPress\WordPressUserManager;

/**
 * Auth Response Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP responses
 * - Manages redirects
 * - Handles error/success responses
 * - Manages HTTP status codes
 */
class AuthResponseHandler
{
    public function __construct(
        private WordPressUserManager $wordPressManager
    ) {
    }

    /**
     * Redirect to URL
     */
    public function redirect(string $url): void
    {
        $this->wordPressManager->redirect($url);
        // exit; // Removed - handled by WordPressUserManager::redirect() via BaseWordPressManager::redirect() which uses TerminationHandler
    }

    /**
     * Redirect to login with redirect_to parameter
     */
    public function redirectToLogin(?string $redirectTo = null): void
    {
        $url = $this->wordPressManager->getHomeUrl('/account/login');
        if ($redirectTo) {
            $url .= '?redirect_to=' . urlencode($redirectTo);
        }
        $this->redirect($url);
    }

    /**
     * Redirect to dashboard
     */
    public function redirectToDashboard(): void
    {
        $this->redirect($this->wordPressManager->getHomeUrl('/account/dashboard'));
    }

    /**
     * Create error response context
     */
    public function createErrorContext(string $pageTitle, string $errorMessage, array $additionalContext = []): array
    {
        return array_merge([
            'page_title' => $pageTitle,
            'error_msg' => $errorMessage,
        ], $additionalContext);
    }

    /**
     * Create success response context
     */
    public function createSuccessContext(
        string $pageTitle,
        string $successMessage,
        array $additionalContext = []
    ): array {
        return array_merge([
            'page_title' => $pageTitle,
            'success_msg' => $successMessage,
        ], $additionalContext);
    }

    /**
     * Create mixed response context (error + success)
     */
    public function createMixedContext(
        string $pageTitle,
        ?string $errorMessage = null,
        ?string $successMessage = null,
        array $additionalContext = []
    ): array {
        return array_merge([
            'page_title' => $pageTitle,
            'error_msg' => $errorMessage,
            'success_msg' => $successMessage,
        ], $additionalContext);
    }
}
