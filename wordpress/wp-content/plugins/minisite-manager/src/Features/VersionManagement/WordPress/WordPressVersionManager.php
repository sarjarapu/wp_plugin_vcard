<?php

namespace Minisite\Features\VersionManagement\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress-specific utilities for version management
 */
class WordPressVersionManager extends BaseWordPressManager implements WordPressManagerInterface
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
     * Get query variable
     */
    public function getQueryVar(string $var, mixed $default = ''): mixed
    {
        return get_query_var($var, $default);
    }

    /**
     * Sanitize text field
     *
     * @param string|null $text Text to sanitize
     * @return string Sanitized text
     */
    public function sanitizeTextField(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return sanitize_text_field($text);
    }

    /**
     * Sanitize textarea field
     *
     * @param string|null $text Text to sanitize
     * @return string Sanitized text
     */
    public function sanitizeTextareaField(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return sanitize_textarea_field($text);
    }

    /**
     * Escape URL
     */
    public function escUrlRaw(string $url): string
    {
        return esc_url_raw($url);
    }

    /**
     * Get home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }

    /**
     * Remove slashes from string
     */
    public function unslash(string $string): string
    {
        return wp_unslash($string);
    }

    /**
     * Send JSON success response
     */
    public function sendJsonSuccess(array $data = array(), int $statusCode = 200): void
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
     * Redirect to URL
     * Uses base class redirect() method which handles termination
     */
    public function redirect(string $location, int $status = 302): void
    {
        parent::redirect($location, $status);
    }

    /**
     * Set HTTP status header
     */
    public function setStatusHeader(int $code): void
    {
        status_header($code);
    }

    /**
     * Set no-cache headers
     */
    public function setNoCacheHeaders(): void
    {
        nocache_headers();
    }

    /**
     * Encode data as JSON
     */
    public function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return wp_json_encode($data, $options, $depth);
    }

    /**
     * Sanitize email
     *
     * @param string|null $email Email to sanitize
     * @return string Sanitized email
     */
    public function sanitizeEmail(?string $email): string
    {
        if ($email === null) {
            return '';
        }

        return sanitize_email($email);
    }

    /**
     * Sanitize URL
     *
     * @param string|null $url URL to sanitize
     * @return string Sanitized URL
     */
    public function sanitizeUrl(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        return esc_url_raw($url);
    }

    /**
     * Verify nonce
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

    /**
     * Get current user
     *
     * @return object|null Current user object or null
     */
    public function getCurrentUser(): ?object
    {
        $user = wp_get_current_user();

        return $user && $user->ID > 0 ? $user : null;
    }

    /**
     * Start database transaction
     */
    public function startTransaction(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction management requires direct queries
        $wpdb->query('START TRANSACTION');
    }

    /**
     * Commit database transaction
     */
    public function commitTransaction(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction management requires direct queries
        $wpdb->query('COMMIT');
    }

    /**
     * Rollback database transaction
     */
    public function rollbackTransaction(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction management requires direct queries
        $wpdb->query('ROLLBACK');
    }

    /**
     * Get minisite repository
     *
     * @return object Minisite repository
     */
    public function getMinisiteRepository(): object
    {
        global $wpdb;

        return new \Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository($wpdb);
    }

    /**
     * Find minisite by ID
     *
     * @param string $id Minisite ID
     * @return object|null Minisite object or null
     */
    public function findMinisiteById(string $id): ?object
    {
        $repo = $this->getMinisiteRepository();

        return $repo->findById($id);
    }

    /**
     * Check if minisite has been published
     *
     * @param string $id Minisite ID
     * @return bool True if published, false otherwise
     */
    public function hasBeenPublished(string $id): bool
    {
        $versionRepo = $GLOBALS['minisite_version_repository'] ?? $this->getVersionRepository();

        return $versionRepo->findPublishedVersion($id) !== null;
    }

    /**
     * Get next version number
     *
     * @param string $id Minisite ID
     * @return int Next version number
     */
    public function getNextVersionNumber(string $id): int
    {
        $versionRepo = $GLOBALS['minisite_version_repository'] ?? $this->getVersionRepository();

        return $versionRepo->getNextVersionNumber($id);
    }

    /**
     * Save version
     *
     * @param object $version Version object
     * @return object Saved version object
     */
    public function saveVersion(object $version): object
    {
        $versionRepo = $GLOBALS['minisite_version_repository'] ?? $this->getVersionRepository();
        $versionRepo->save($version);

        return $version;
    }

    /**
     * Get VersionRepository instance (from global or create if needed)
     *
     * @return \Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface
     */
    private function getVersionRepository(): \Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface
    {
        // Try to get from global first (Doctrine-based repository)
        if (isset($GLOBALS['minisite_version_repository'])) {
            return $GLOBALS['minisite_version_repository'];
        }

        // Fallback: Create old repository if Doctrine not available
        // This provides backward compatibility during migration
        global $wpdb;
        return new \Minisite\Infrastructure\Persistence\Repositories\VersionRepository($wpdb);
    }

    /**
     * Update business info
     *
     * @param string $siteId Site ID
     * @param array $fields Fields to update
     * @param int $userId User ID
     */
    public function updateBusinessInfo(string $siteId, array $fields, int $userId): void
    {
        $repo = $this->getMinisiteRepository();
        $repo->updateBusinessInfo($siteId, $fields, $userId);
    }

    /**
     * Update coordinates
     *
     * @param string $siteId Site ID
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $userId User ID
     */
    public function updateCoordinates(string $siteId, float $lat, float $lng, int $userId): void
    {
        $repo = $this->getMinisiteRepository();
        $repo->updateCoordinates($siteId, $lat, $lng, $userId);
    }

    /**
     * Update title
     *
     * @param string $siteId Site ID
     * @param string $title Title
     */
    public function updateTitle(string $siteId, string $title): void
    {
        $repo = $this->getMinisiteRepository();
        $repo->updateTitle($siteId, $title);
    }

    /**
     * Update multiple minisite fields
     *
     * @param string $siteId Site ID
     * @param array $fields Fields to update
     * @param int $userId User ID
     */
    public function updateMinisiteFields(string $siteId, array $fields, int $userId): void
    {
        $repo = $this->getMinisiteRepository();
        $repo->updateMinisiteFields($siteId, $fields, $userId);
    }
}
