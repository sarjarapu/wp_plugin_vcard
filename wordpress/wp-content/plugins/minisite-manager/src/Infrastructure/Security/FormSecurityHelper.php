<?php

namespace Minisite\Infrastructure\Security;

use Minisite\Domain\Interfaces\WordPressManagerInterface;

/**
 * Form Security Helper
 *
 * SINGLE RESPONSIBILITY: Handle form security operations
 * - Nonce verification for form submissions
 * - Safe POST data retrieval with proper sanitization
 * - Consistent security patterns across controllers
 */
class FormSecurityHelper
{
    public function __construct(
        private WordPressManagerInterface $wordPressManager
    ) {
    }

    /**
     * Verify nonce for form submissions
     */
    public function verifyNonce(string $action, string $nonceField = 'minisite_edit_nonce'): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in the next line
        if (! isset($_POST[$nonceField])) {
            return false;
        }

        $nonce = $this->getPostData($nonceField);

        return $this->wordPressManager->verifyNonce($nonce, $action);
    }

    /**
     * Safely get and sanitize POST data as text
     */
    public function getPostData(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, unslashing handled by WordPressManager
        $value = $_POST[$key] ?? $default;

        return $this->wordPressManager->sanitizeTextField($value);
    }

    /**
     * Safely get and sanitize POST data as integer
     */
    public function getPostDataInt(string $key, int $default = 0): int
    {
        return (int) $this->getPostData($key, (string) $default);
    }

    /**
     * Safely get and sanitize POST data as URL
     */
    public function getPostDataUrl(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, unslashing handled by WordPressManager
        $value = $_POST[$key] ?? $default;

        return $this->wordPressManager->sanitizeUrl($value);
    }

    /**
     * Safely get and sanitize POST data as email
     */
    public function getPostDataEmail(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, unslashing handled by WordPressManager
        $value = $_POST[$key] ?? $default;

        return $this->wordPressManager->sanitizeEmail($value);
    }

    /**
     * Safely get and sanitize POST data as textarea
     */
    public function getPostDataTextarea(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, unslashing handled by WordPressManager
        $value = $_POST[$key] ?? $default;

        return $this->wordPressManager->sanitizeTextareaField($value);
    }

    /**
     * Check if this is a POST request
     */
    public function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Verify nonce and check POST request in one call
     */
    public function isValidFormSubmission(string $action, string $nonceField = 'minisite_edit_nonce'): bool
    {
        return $this->isPostRequest() && $this->verifyNonce($action, $nonceField);
    }
}
