<?php

namespace Minisite\Features\NewMinisite\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress New Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific new minisite operations
 * - Manages WordPress database interactions for new minisite creation
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 */
class WordPressNewMinisiteManager extends BaseWordPressManager implements WordPressManagerInterface
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
        return wp_login_url($this->getHomeUrl('/account/sites/new'));
    }

    /**
     * Check if user can create new minisites
     */
    public function userCanCreateMinisite(int $userId): bool
    {
        // For now, allow all logged-in users to create minisites
        // This can be extended with subscription checks, limits, etc.
        return user_can($userId, 'read');
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
     * Get next version number (required by interface, but not used in NewMinisite)
     * Services should inject VersionRepositoryInterface directly instead
     */
    public function getNextVersionNumber(string $minisiteId): int
    {
        // Not used - services should inject VersionRepositoryInterface directly
        // Return 1 as default
        return 1;
    }

    /**
     * Save version (required by interface, but not used in NewMinisite)
     * Services should inject VersionRepositoryInterface directly instead
     */
    public function saveVersion(object $version): object
    {
        // Not used - services should inject VersionRepositoryInterface directly
        // Return version as-is
        return $version;
    }

    /**
     * Check if minisite has been published (always false for new minisites)
     */
    public function hasBeenPublished(string $siteId): bool
    {
        // New minisites have never been published
        return false;
    }

    /**
     * Find minisite by ID (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function findMinisiteById(string $siteId): ?object
    {
        // Not used - services should inject MinisiteRepository directly
        return null;
    }

    /**
     * Update business info (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function updateBusinessInfo(string $siteId, array $fields, int $userId): void
    {
        // Not used - services should inject MinisiteRepository directly
    }

    /**
     * Update coordinates (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function updateCoordinates(string $siteId, float $lat, float $lng, int $userId): void
    {
        // Not used - services should inject MinisiteRepository directly
    }

    /**
     * Update title (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function updateTitle(string $siteId, string $title): void
    {
        // Not used - services should inject MinisiteRepository directly
    }

    /**
     * Update multiple minisite fields (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function updateMinisiteFields(string $siteId, array $fields, int $userId): void
    {
        // Not used - services should inject MinisiteRepository directly
    }

    /**
     * Get minisite repository (required by interface, but not used)
     * Services should inject MinisiteRepository directly instead
     */
    public function getMinisiteRepository(): object
    {
        // Not used - services should inject MinisiteRepository directly
        global $wpdb;
        return new \Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository($wpdb);
    }
}
