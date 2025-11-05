<?php

namespace Minisite\Features\PublishMinisite\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
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
    private ?MinisiteRepository $minisiteRepository = null;

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
     * Get minisite repository instance
     */
    public function getMinisiteRepository(): MinisiteRepository
    {
        if ($this->minisiteRepository === null) {
            $this->minisiteRepository = new MinisiteRepository(db::getWpdb());
        }

        return $this->minisiteRepository;
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
     * Find minisite by ID
     */
    public function findMinisiteById(string $minisiteId): ?object
    {
        return $this->getMinisiteRepository()->findById($minisiteId);
    }

    /**
     * Get next version number
     */
    public function getNextVersionNumber(string $minisiteId): int
    {
        // Not needed for publish feature, but required by interface
        // Return 1 as default since publish doesn't create versions
        return 1;
    }

    /**
     * Save version
     */
    public function saveVersion(object $version): object
    {
        // Not needed for publish feature, but required by interface
        // Return version as-is
        return $version;
    }

    /**
     * Check if minisite has been published
     */
    public function hasBeenPublished(string $siteId): bool
    {
        $minisite = $this->findMinisiteById($siteId);

        return $minisite !== null && $minisite->status === 'published';
    }

    /**
     * Update business info fields
     */
    public function updateBusinessInfo(string $siteId, array $fields, int $userId): void
    {
        $this->getMinisiteRepository()->updateBusinessInfo($siteId, $fields, $userId);
    }

    /**
     * Update coordinates
     */
    public function updateCoordinates(string $siteId, float $lat, float $lng, int $userId): void
    {
        $this->getMinisiteRepository()->updateCoordinates($siteId, $lat, $lng, $userId);
    }

    /**
     * Update title
     */
    public function updateTitle(string $siteId, string $title): void
    {
        $this->getMinisiteRepository()->updateTitle($siteId, $title);
    }

    /**
     * Update multiple minisite fields in a single operation
     */
    public function updateMinisiteFields(string $siteId, array $fields, int $userId): void
    {
        $this->getMinisiteRepository()->updateMinisiteFields($siteId, $fields, $userId);
    }

    /**
     * Start database transaction
     */
    public function startTransaction(): void
    {
        db::query('START TRANSACTION');
    }

    /**
     * Commit database transaction
     */
    public function commitTransaction(): void
    {
        db::query('COMMIT');
    }

    /**
     * Rollback database transaction
     */
    public function rollbackTransaction(): void
    {
        db::query('ROLLBACK');
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
