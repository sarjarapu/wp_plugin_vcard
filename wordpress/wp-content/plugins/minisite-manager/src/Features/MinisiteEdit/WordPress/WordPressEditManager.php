<?php

namespace Minisite\Features\MinisiteEdit\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress Edit Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific edit operations
 * - Manages WordPress database interactions for editing
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 */
class WordPressEditManager extends BaseWordPressManager implements WordPressManagerInterface
{
    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?object
    {
        return wp_get_current_user();
    }

    /**
     * Get query variable
     */
    public function getQueryVar(string $var, $default = '')
    {
        return get_query_var($var, $default);
    }

    /**
     * Sanitize text field
     */
    public function sanitizeTextField(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return sanitize_text_field(wp_unslash($text));
    }

    /**
     * Sanitize textarea field
     */
    public function sanitizeTextareaField(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return sanitize_textarea_field(wp_unslash($text));
    }

    /**
     * Sanitize URL field
     */
    public function sanitizeUrl(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        return esc_url_raw(wp_unslash($url));
    }

    /**
     * Sanitize email field
     */
    public function sanitizeEmail(?string $email): string
    {
        if ($email === null) {
            return '';
        }

        return sanitize_email(wp_unslash($email));
    }

    /**
     * Verify nonce
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Create nonce
     */
    public function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Redirect to URL
     * Uses base class redirect() method which handles termination
     */
    public function redirect(string $url, int $status = 302): void
    {
        parent::redirect($url, $status);
    }

    /**
     * Get home URL
     */
    public function getHomeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    /**
     * Get login redirect URL
     */
    public function getLoginRedirectUrl(): string
    {
        $currentUrl = isset($_SERVER['REQUEST_URI']) ?
            sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        return $this->getHomeUrl('/account/login?redirect_to=' . urlencode($currentUrl));
    }

    /**
     * Check if user owns minisite
     */
    public function userOwnsMinisite(object $minisite, int $userId): bool
    {
        return $minisite->createdBy === $userId;
    }
}
