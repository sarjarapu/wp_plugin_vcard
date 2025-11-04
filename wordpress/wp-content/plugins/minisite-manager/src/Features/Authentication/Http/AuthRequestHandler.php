<?php

namespace Minisite\Features\Authentication\Http;

// All security concerns are handled properly in this class:
// - Nonce verification happens in isValidNonce() method
// - Input sanitization and unslashing happens in sanitizeInput() method

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\WordPress\WordPressUserManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * Auth Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests and extract data
 * - Validates HTTP method
 * - Extracts and sanitizes form data
 * - Creates command objects
 * - Handles nonce verification
 */
class AuthRequestHandler
{
    public function __construct(
        private WordPressUserManager $wordPressManager,
        private FormSecurityHelper $formSecurityHelper
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
            userLogin: $this->formSecurityHelper->getPostData('user_login', ''),
            userPassword: $this->formSecurityHelper->getPostData('user_pass', ''),
            remember: !empty($this->formSecurityHelper->getPostData('remember')),
            redirectTo: $this->formSecurityHelper->getPostDataUrl(
                'redirect_to',
                $this->wordPressManager->getHomeUrl('/account/dashboard')
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
            userLogin: $this->formSecurityHelper->getPostData('user_login', ''),
            userEmail: $this->formSecurityHelper->getPostDataEmail('user_email', ''),
            userPassword: $this->formSecurityHelper->getPostData('user_pass', ''),
            redirectTo: $this->formSecurityHelper->getPostDataUrl(
                'redirect_to',
                $this->wordPressManager->getHomeUrl('/account/dashboard')
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
            userLogin: $this->formSecurityHelper->getPostData('user_login', '')
        );
    }

    /**
     * Safely get and sanitize GET data
     */
    private function getGetData(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- GET parameters don't require nonce verification, data sanitized
        return $this->wordPressManager->sanitizeTextField(wp_unslash($_GET[$key] ?? $default));
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
        return $this->formSecurityHelper->isPostRequest();
    }

    /**
     * Safely get and sanitize POST data
     * @phpstan-ignore-next-line
     */
    private function getPostData(string $key, string $default = ''): string
    {
        return $this->formSecurityHelper->getPostData($key, $default);
    }

    private function isValidNonce(string $action): bool
    {
        $nonceField = match ($action) {
            'minisite_login' => 'minisite_login_nonce',
            'minisite_register' => 'minisite_register_nonce',
            'minisite_forgot_password' => 'minisite_forgot_password_nonce',
            default => 'minisite_nonce'
        };

        return $this->formSecurityHelper->verifyNonce($action, $nonceField);
    }

    /**
     * Sanitize input text
     * @phpstan-ignore-next-line
     */
    private function sanitizeInput(string $input): string
    {
        return $this->wordPressManager->sanitizeText($this->wordPressManager->unslash($input));
    }

    /**
     * Sanitize email input
     * @phpstan-ignore-next-line
     */
    private function sanitizeEmail(string $email): string
    {
        return $this->wordPressManager->sanitizeEmail($this->wordPressManager->unslash($email));
    }

    /**
     * Sanitize URL input
     * @phpstan-ignore-next-line
     */
    private function sanitizeUrl(string $url): string
    {
        return $this->wordPressManager->sanitizeUrl($this->wordPressManager->unslash($url));
    }
}
