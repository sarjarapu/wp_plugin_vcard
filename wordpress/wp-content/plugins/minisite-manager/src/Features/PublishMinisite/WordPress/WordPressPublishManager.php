<?php

namespace Minisite\Features\PublishMinisite\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress Publish Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific publish operations
 * - Manages WordPress database interactions for publishing
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 */
class WordPressPublishManager extends BaseWordPressManager implements WordPressManagerInterface
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
     * Get current user ID
     */
    public function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Get query variable
     */
    public function getQueryVar(string $var, $default = '')
    {
        return get_query_var($var, $default);
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
     * Get admin URL
     */
    public function getAdminUrl(string $path = '', string $scheme = 'admin'): string
    {
        // phpstan-ignore-next-line -- WordPress admin_url() accepts 2 parameters (path, scheme)
        return admin_url($path, $scheme);
    }

    /**
     * Send JSON success response
     */
    public function sendJsonSuccess($data = null, ?int $statusCode = null): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public function sendJsonError($data = null, ?int $statusCode = null): void
    {
        wp_send_json_error($data, $statusCode);
    }

    /**
     * Create nonce
     */
    public function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Verify nonce
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
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
     * Check if WooCommerce is active
     */
    public function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }


    /**
     * Get POST data (unslashed, caller should sanitize)
     */
    public function getPostData(string $key, $default = null)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller is responsible for nonce verification and sanitization
        if (! isset($_POST[$key])) {
            return $default;
        }

        return wp_unslash($_POST[$key]);
        // phpcs:enable
    }

    /**
     * Check if request is POST
     */
    public function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Check if request is AJAX
     */
    public function isAjaxRequest(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}
