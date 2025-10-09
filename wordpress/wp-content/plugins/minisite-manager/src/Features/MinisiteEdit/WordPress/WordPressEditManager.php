<?php

namespace Minisite\Features\MinisiteEdit\WordPress;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress Edit Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific edit operations
 * - Manages WordPress database interactions for editing
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 */
class WordPressEditManager
{
    private ?MinisiteRepository $minisiteRepository = null;
    private ?VersionRepository $versionRepository = null;

    /**
     * Get minisite repository instance
     */
    private function getMinisiteRepository(): MinisiteRepository
    {
        if ($this->minisiteRepository === null) {
            $this->minisiteRepository = new MinisiteRepository(db::getWpdb());
        }
        return $this->minisiteRepository;
    }

    /**
     * Get version repository instance
     */
    private function getVersionRepository(): VersionRepository
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
    public function sanitizeTextField(string $text): string
    {
        return sanitize_text_field($text);
    }

    /**
     * Sanitize textarea field
     */
    public function sanitizeTextareaField(string $text): string
    {
        return sanitize_textarea_field($text);
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
     */
    public function redirect(string $url): void
    {
        wp_redirect($url);
        exit;
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
     * Find minisite by ID
     */
    public function findMinisiteById(string $siteId): ?object
    {
        return $this->getMinisiteRepository()->findById($siteId);
    }

    /**
     * Find version by ID
     */
    public function findVersionById(int $versionId): ?object
    {
        return $this->getVersionRepository()->findById($versionId);
    }

    /**
     * Get latest draft for editing
     */
    public function getLatestDraftForEditing(string $siteId): ?object
    {
        return $this->getVersionRepository()->getLatestDraftForEditing($siteId);
    }

    /**
     * Find latest draft
     */
    public function findLatestDraft(string $siteId): ?object
    {
        return $this->getVersionRepository()->findLatestDraft($siteId);
    }

    /**
     * Get next version number
     */
    public function getNextVersionNumber(string $siteId): int
    {
        return $this->getVersionRepository()->getNextVersionNumber($siteId);
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
     * Find published version
     */
    public function findPublishedVersion(string $siteId): ?object
    {
        return $this->getVersionRepository()->findPublishedVersion($siteId);
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
     * Check if minisite has been published
     */
    public function hasBeenPublished(string $siteId): bool
    {
        return $this->findPublishedVersion($siteId) !== null;
    }

    /**
     * Check if user owns minisite
     */
    public function userOwnsMinisite(object $minisite, int $userId): bool
    {
        return $minisite->createdBy === $userId;
    }
}
