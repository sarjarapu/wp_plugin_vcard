<?php

namespace Minisite\Domain\Services;

/**
 * Service for generating unique IDs and managing slug generation for minisites
 */
final class MinisiteIdGenerator
{
    /**
     * Generate a unique 16-byte hex ID for minisites
     * 
     * @return string 32-character hex string (e.g., "a1b2c3d4e5f6789012345678901234ab")
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a temporary slug for draft minisites
     * 
     * @param string $id The minisite ID
     * @return string Temporary slug (e.g., "draft-a1b2c3d4e5f6")
     */
    public static function generateTempSlug(string $id): string
    {
        return 'draft-' . substr($id, 0, 12);
    }

    /**
     * Generate a temporary slug with custom prefix
     * 
     * @param string $id The minisite ID
     * @param string $prefix Custom prefix (default: 'draft')
     * @return string Temporary slug
     */
    public static function generateTempSlugWithPrefix(string $id, string $prefix = 'draft'): string
    {
        return $prefix . '-' . substr($id, 0, 12);
    }

    /**
     * Validate if a string is a valid minisite ID format
     * 
     * @param string $id The ID to validate
     * @return bool True if valid format
     */
    public static function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $id) === 1;
    }

    /**
     * Validate if a string is a valid temporary slug format
     * 
     * @param string $slug The slug to validate
     * @return bool True if valid temporary slug format
     */
    public static function isValidTempSlug(string $slug): bool
    {
        return preg_match('/^draft-[a-f0-9]{12}$/', $slug) === 1;
    }

    /**
     * Extract the ID from a temporary slug
     * 
     * @param string $tempSlug The temporary slug
     * @return string|null The extracted ID or null if invalid
     */
    public static function extractIdFromTempSlug(string $tempSlug): ?string
    {
        if (!self::isValidTempSlug($tempSlug)) {
            return null;
        }
        
        $idPart = substr($tempSlug, 6); // Remove 'draft-' prefix
        return $idPart . str_repeat('0', 20); // Pad to 32 characters
    }

    /**
     * Generate a random slug for testing/development
     * 
     * @return string Random slug
     */
    public static function generateRandomSlug(): string
    {
        $id = self::generate();
        return self::generateTempSlug($id);
    }
}
