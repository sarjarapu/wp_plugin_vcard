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
     * NOTE: This is still used in MinisiteFormProcessor and will be refactored in Phase 2
     */
    public function findMinisiteById(string $siteId): ?object;

    /**
     * Update multiple minisite fields in a single operation
     * NOTE: This is still used in MinisiteDatabaseCoordinator and will be refactored in Phase 2
     */
    public function updateMinisiteFields(string $siteId, array $fields, int $userId): void;

    /**
     * Get minisite repository
     * NOTE: This is still used in multiple services and will be refactored in Phase 2
     */
    public function getMinisiteRepository(): object;
}
