<?php

namespace Minisite\Features\Authentication\Services;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\WordPress\WordPressUserManager;

/**
 * Authentication Service
 *
 * Handles all authentication business logic including login, registration,
 * password reset, and user session management.
 */
final class AuthService
{
    public function __construct(
        private WordPressUserManager $wordPressManager
    ) {
    }
    /**
     * Authenticate user with credentials
     *
     * @param LoginCommand $command
     * @return array{success: bool, error?: string, user?: \WP_User, redirect_to?: string}
     */
    public function login(LoginCommand $command): array
    {
        if (!$this->validateLoginCredentials($command)) {
            return [
                'success' => false,
                'error' => 'Please enter both username/email and password.'
            ];
        }

        $creds = [
            'user_login' => $command->userLogin,
            'user_password' => $command->userPassword,
            'remember' => $command->remember,
        ];

        $user = $this->wordPressManager->signon($creds, false);

        if ($this->wordPressManager->isWpError($user)) {
            return [
                'success' => false,
                'error' => $user->get_error_message()
            ];
        }

        return [
            'success' => true,
            'user' => $user,
            'redirect_to' => $command->redirectTo
        ];
    }

    /**
     * Register a new user
     *
     * @param RegisterCommand $command
     * @return array{success: bool, error?: string, user?: \WP_User, redirect_to?: string}
     */
    public function register(RegisterCommand $command): array
    {
        $validation = $this->validateRegistrationData($command);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        $user_id = $this->wordPressManager->createUser(
            $command->userLogin,
            $command->userPassword,
            $command->userEmail
        );

        if ($this->wordPressManager->isWpError($user_id)) {
            return [
                'success' => false,
                'error' => $user_id->get_error_message()
            ];
        }

        // Auto-login the new user
        $user = $this->wordPressManager->getUserBy('id', $user_id);
        $this->wordPressManager->setCurrentUser($user_id);
        $this->wordPressManager->setAuthCookie($user_id);

        return [
            'success' => true,
            'user' => $user,
            'redirect_to' => $command->redirectTo
        ];
    }

    /**
     * Send password reset email
     *
     * @param ForgotPasswordCommand $command
     * @return array{success: bool, error?: string, message?: string}
     */
    public function forgotPassword(ForgotPasswordCommand $command): array
    {
        if (empty($command->userLogin)) {
            return [
                'success' => false,
                'error' => 'Please enter your username or email address.'
            ];
        }

        $user = $this->findUserByLoginOrEmail($command->userLogin);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid username or email address.'
            ];
        }

        $result = $this->wordPressManager->retrievePassword($user->user_login);

        if ($this->wordPressManager->isWpError($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => 'Password reset email sent. Please check your inbox.'
        ];
    }

    /**
     * Logout current user
     */
    public function logout(): void
    {
        $this->wordPressManager->logout();
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return $this->wordPressManager->isUserLoggedIn();
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?\WP_User
    {
        $user = $this->wordPressManager->getCurrentUser();
        return $user->ID ? $user : null;
    }

    /**
     * Validate login credentials
     */
    private function validateLoginCredentials(LoginCommand $command): bool
    {
        return !empty($command->userLogin) && !empty($command->userPassword);
    }

    /**
     * Validate registration data
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateRegistrationData(RegisterCommand $command): array
    {
        if (empty($command->userLogin) || empty($command->userEmail) || empty($command->userPassword)) {
            return [
                'valid' => false,
                'error' => 'Please fill in all required fields.'
            ];
        }

        if (!$this->wordPressManager->isEmail($command->userEmail)) {
            return [
                'valid' => false,
                'error' => 'Please enter a valid email address.'
            ];
        }

        if (strlen($command->userPassword) < 6) {
            return [
                'valid' => false,
                'error' => 'Password must be at least 6 characters long.'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Find user by login or email
     */
    private function findUserByLoginOrEmail(string $login): ?\WP_User
    {
        $user = $this->wordPressManager->getUserBy('login', $login);
        if (!$user) {
            $user = $this->wordPressManager->getUserBy('email', $login);
        }
        return $user ?: null;
    }
}
