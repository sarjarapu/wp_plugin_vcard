<?php

namespace Minisite\Features\Authentication\Http;

// All security concerns are handled properly in this class:
// - Nonce verification happens in isValidNonce() method
// - Input sanitization and unslashing happens in sanitizeInput() method

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\WordPress\WordPressUserManager;

/**
 * Auth Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests and extract data
 * - Validates HTTP method
 * - Extracts and sanitizes form data
 * - Creates command objects
 * - Handles nonce verification
 */
final class AuthRequestHandler
{
    public function __construct(
        private WordPressUserManager $wordPressManager
    ) {
    }

    /**
     * Handle login request
     */
    public function handleLoginRequest(): ?LoginCommand
    {
        if (!$this->isPostRequest()) {
            return null;
        }

        if (!$this->isValidNonce('minisite_login')) {
            throw new \InvalidArgumentException('Invalid nonce');
        }

        return new LoginCommand(
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeInput()
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? ''),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeInput()
            userPassword: $this->sanitizeInput($_POST['user_pass'] ?? ''),
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce()
            remember: isset($_POST['remember']),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeUrl()
            redirectTo: $this->sanitizeUrl(
                $_POST['redirect_to'] ?? $this->wordPressManager->getHomeUrl('/account/dashboard')
            )
        );
    }

    /**
     * Handle registration request
     */
    public function handleRegisterRequest(): ?RegisterCommand
    {
        if (!$this->isPostRequest()) {
            return null;
        }

        if (!$this->isValidNonce('minisite_register')) {
            throw new \InvalidArgumentException('Invalid nonce');
        }

        return new RegisterCommand(
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeInput()
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? ''),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeEmail()
            userEmail: $this->sanitizeEmail($_POST['user_email'] ?? ''),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeInput()
            userPassword: $this->sanitizeInput($_POST['user_pass'] ?? ''),
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeUrl()
            redirectTo: $this->sanitizeUrl(
                $_POST['redirect_to'] ?? $this->wordPressManager->getHomeUrl('/account/dashboard')
            )
        );
    }

    /**
     * Handle forgot password request
     */
    public function handleForgotPasswordRequest(): ?ForgotPasswordCommand
    {
        if (!$this->isPostRequest()) {
            return null;
        }

        if (!$this->isValidNonce('minisite_forgot_password')) {
            throw new \InvalidArgumentException('Invalid nonce');
        }

        return new ForgotPasswordCommand(
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Security handled in isValidNonce() and sanitizeInput()
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? '')
        );
    }

    /**
     * Safely get and sanitize GET data
     */
    private function getGetData(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- GET parameters don't require nonce verification, data sanitized
        return $this->wordPressManager->sanitizeText(wp_unslash($_GET[$key] ?? $default));
    }

    /**
     * Get redirect URL from query parameter
     */
    public function getRedirectTo(): string
    {
        return $this->getGetData('redirect_to', $this->wordPressManager->getHomeUrl('/account/dashboard'));
    }

    private function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Safely get and sanitize POST data
     */
    private function getPostData(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified before calling this method
        return $this->wordPressManager->sanitizeText(wp_unslash($_POST[$key] ?? $default));
    }

    private function isValidNonce(string $action): bool
    {
        $nonceField = match ($action) {
            'minisite_login' => 'minisite_login_nonce',
            'minisite_register' => 'minisite_register_nonce',
            'minisite_forgot_password' => 'minisite_forgot_password_nonce',
            default => 'minisite_nonce'
        };

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in the next line
        return isset($_POST[$nonceField]) &&
               $this->wordPressManager->verifyNonce($this->getPostData($nonceField), $action);
    }

    private function sanitizeInput(string $input): string
    {
        return $this->wordPressManager->sanitizeText($this->wordPressManager->unslash($input));
    }

    private function sanitizeEmail(string $email): string
    {
        return $this->wordPressManager->sanitizeEmail($this->wordPressManager->unslash($email));
    }

    private function sanitizeUrl(string $url): string
    {
        return $this->wordPressManager->sanitizeUrl($this->wordPressManager->unslash($url));
    }
}
