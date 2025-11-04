<?php

namespace Minisite\Features\NewMinisite\WordPress;

use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

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
    private ?MinisiteRepository $minisiteRepository = null;
    private ?VersionRepository $versionRepository = null;

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
     * Get version repository instance
     */
    public function getVersionRepository(): VersionRepository
    {
        if ($this->versionRepository === null) {
            $this->versionRepository = new VersionRepository(db::getWpdb());
        }
        return $this->versionRepository;
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
        // exit; // Removed - handled by BaseWordPressManager::redirect() via TerminationHandler
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
     * Get user's minisite count
     */
    public function getUserMinisiteCount(int $userId): int
    {
        return $this->getMinisiteRepository()->countByOwner($userId);
    }

    /**
     * Get next version number for new minisite
     */
    public function getNextVersionNumber(string $minisiteId): int
    {
        return $this->getVersionRepository()->getNextVersionNumber($minisiteId);
    }

    /**
     * Save version
     */
    public function saveVersion(object $version): object
    {
        return $this->getVersionRepository()->save($version);
    }

    /**
     * Update business info
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
     * Find minisite by ID
     */
    public function findMinisiteById(string $siteId): ?object
    {
        return $this->getMinisiteRepository()->findById($siteId);
    }

    /**
     * Check if minisite has been published (always false for new minisites)
     */
    public function hasBeenPublished(string $siteId): bool
    {
        // New minisites have never been published
        return false;
    }
}
