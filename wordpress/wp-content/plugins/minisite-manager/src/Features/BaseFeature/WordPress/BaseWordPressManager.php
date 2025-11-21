<?php

namespace Minisite\Features\BaseFeature\WordPress;

use Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Base WordPress Manager Abstract Class
 *
 * SINGLE RESPONSIBILITY: Provide common WordPress operations for all managers
 * - Manages termination handler injection
 * - Provides consistent redirect behavior with termination
 * - Centralizes common WordPress function wrappers
 * - Standardizes sanitization, authentication, and nonce handling
 *
 * All WordPress managers should extend this class to ensure consistent
 * behavior and reduce code duplication.
 */
abstract class BaseWordPressManager implements WordPressManagerInterface
{
    /**
     * Termination handler for preventing WordPress from loading default templates after redirect
     */
    protected TerminationHandlerInterface $terminationHandler;

    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        $this->terminationHandler = $terminationHandler;
    }

    // ===== REDIRECT METHODS =====

    /**
     * Redirect to URL and terminate script execution
     *
     * This follows WordPress best practice: wp_redirect() + exit.
     * In production, this calls exit() after redirect. In tests, it's a no-op.
     *
     * @param string $url URL to redirect to
     * @param int $status HTTP status code (default: 302)
     * @return void
     */
    public function redirect(string $url, int $status = 302): void
    {
        wp_redirect($url, $status);
        // Terminate after redirect (inherited termination handler)
        // In production: calls exit(). In tests: no-op, allowing tests to continue
        $this->terminationHandler->terminate();
    }

    // ===== SANITIZATION METHODS (Required by Interface) =====

    /**
     * Sanitize text field
     *
     * Standardizes null handling and wp_unslash usage.
     * WordPress adds slashes to POST data, so we unslash before sanitizing.
     *
     * @param string|null $text Text to sanitize
     * @return string Sanitized text (empty string if null)
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
     *
     * Standardizes null handling and wp_unslash usage.
     *
     * @param string|null $text Text to sanitize
     * @return string Sanitized text (empty string if null)
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
     *
     * Standardizes null handling and wp_unslash usage.
     *
     * @param string|null $url URL to sanitize
     * @return string Sanitized URL (empty string if null)
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
     *
     * Standardizes null handling and wp_unslash usage.
     *
     * @param string|null $email Email to sanitize
     * @return string Sanitized email (empty string if null)
     */
    public function sanitizeEmail(?string $email): string
    {
        if ($email === null) {
            return '';
        }

        return sanitize_email(wp_unslash($email));
    }

    // ===== USER AUTHENTICATION METHODS =====

    /**
     * Check if user is logged in
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     *
     * Standardizes null check for user with ID > 0.
     * WordPress may return user object with ID 0 for logged-out users.
     *
     * @return object|null Current user object or null if not logged in
     */
    public function getCurrentUser(): ?object
    {
        $user = wp_get_current_user();

        return $user && $user->ID > 0 ? $user : null;
    }

    // ===== NONCE METHODS (Required by Interface) =====

    /**
     * Verify nonce
     *
     * Standardizes boolean return (wp_verify_nonce returns int|false).
     *
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool True if valid, false otherwise
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Create nonce
     *
     * @param string $action Action name
     * @return string Nonce value
     */
    public function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    // ===== URL AND QUERY METHODS =====

    /**
     * Get home URL
     *
     * @param string $path Optional path to append
     * @return string Home URL
     */
    public function getHomeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    /**
     * Get query variable
     *
     * @param string $var Variable name
     * @param mixed $default Default value
     * @return mixed Query variable value
     */
    public function getQueryVar(string $var, mixed $default = ''): mixed
    {
        return get_query_var($var, $default);
    }

    // ===== COMMON UTILITIES =====

    /**
     * Remove slashes from string
     *
     * WordPress adds slashes to POST data. This method removes them.
     *
     * @param string $string String to unslash
     * @return string Unslashed string
     */
    public function unslash(string $string): string
    {
        return wp_unslash($string);
    }

    /**
     * Escape URL for database storage
     *
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    public function escUrlRaw(string $url): string
    {
        return esc_url_raw($url);
    }
}
