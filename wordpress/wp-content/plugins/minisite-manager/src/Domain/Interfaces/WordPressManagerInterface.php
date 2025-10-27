<?php

namespace Minisite\Domain\Interfaces;

/**
 * WordPress Manager Interface
 *
 * SINGLE RESPONSIBILITY: Define common interface for WordPress managers
 * - Ensures consistent interface across different feature managers
 * - Enables shared components to work with different managers
 * - Provides type safety for dependency injection
 */
interface WordPressManagerInterface
{
    /**
     * Get current user
     */
    public function getCurrentUser(): ?object;

    /**
     * Sanitize text field
     */
    public function sanitizeTextField(?string $text): string;

    /**
     * Sanitize textarea field
     */
    public function sanitizeTextareaField(?string $text): string;

    /**
     * Sanitize URL field
     */
    public function sanitizeUrl(?string $url): string;

    /**
     * Sanitize email field
     */
    public function sanitizeEmail(?string $email): string;

    /**
     * Verify nonce
     */
    public function verifyNonce(string $nonce, string $action): bool;

    /**
     * Create nonce
     */
    public function createNonce(string $action): string;

    /**
     * Get home URL
     */
    public function getHomeUrl(string $path = ''): string;

    /**
     * Find minisite by ID
     */
    public function findMinisiteById(string $siteId): ?object;

    /**
     * Get next version number
     */
    public function getNextVersionNumber(string $minisiteId): int;

    /**
     * Save version
     */
    public function saveVersion(object $version): object;

    /**
     * Check if minisite has been published
     */
    public function hasBeenPublished(string $siteId): bool;

    /**
     * Update business info fields
     */
    public function updateBusinessInfo(string $siteId, array $fields, int $userId): void;

    /**
     * Update coordinates
     */
    public function updateCoordinates(string $siteId, float $lat, float $lng, int $userId): void;

    /**
     * Update title
     */
    public function updateTitle(string $siteId, string $title): void;

    /**
     * Update multiple minisite fields in a single operation
     */
    public function updateMinisiteFields(string $siteId, array $fields, int $userId): void;

    /**
     * Start database transaction
     */
    public function startTransaction(): void;

    /**
     * Commit database transaction
     */
    public function commitTransaction(): void;

    /**
     * Rollback database transaction
     */
    public function rollbackTransaction(): void;

    /**
     * Get minisite repository
     */
    public function getMinisiteRepository(): object;
}
