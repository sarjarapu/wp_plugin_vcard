<?php

namespace Minisite\Features\PublishMinisite\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

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

    // ===== PUBLISH-SPECIFIC METHODS ONLY =====

    /**
     * Get current user ID
     *
     * Convenience method for getting user ID.
     *
     * @return int Current user ID (0 if not logged in)
     */
    public function getCurrentUserId(): int
    {
        return get_current_user_id();
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
