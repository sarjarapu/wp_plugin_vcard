<?php

namespace Minisite\Features\Authentication\Http;

// All security concerns are handled properly in this class:
// - Nonce verification happens in isValidNonce() method
// - Input sanitization and unslashing happens in sanitizeInput() method

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;

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
            redirectTo: $this->sanitizeUrl($_POST['redirect_to'] ?? home_url('/account/dashboard'))
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
            redirectTo: $this->sanitizeUrl($_POST['redirect_to'] ?? home_url('/account/dashboard'))
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
     * Get redirect URL from query parameter
     */
    public function getRedirectTo(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for redirect URL doesn't require nonce verification
        return sanitize_text_field(wp_unslash($_GET['redirect_to'] ?? home_url('/account/dashboard')));
    }

    private function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private function isValidNonce(string $action): bool
    {
        $nonceField = match ($action) {
            'minisite_login' => 'minisite_login_nonce',
            'minisite_register' => 'minisite_register_nonce',
            'minisite_forgot_password' => 'minisite_forgot_password_nonce',
            default => 'minisite_nonce'
        };

        return isset($_POST[$nonceField]) &&
               wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceField])), $action);
    }

    private function sanitizeInput(string $input): string
    {
        return sanitize_text_field(wp_unslash($input));
    }

    private function sanitizeEmail(string $email): string
    {
        return sanitize_email(wp_unslash($email));
    }

    private function sanitizeUrl(string $url): string
    {
        return sanitize_url(wp_unslash($url));
    }
}
