<?php

namespace Minisite\Features\Authentication\Services;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;

/**
 * Authentication Service
 * 
 * Handles all authentication business logic including login, registration,
 * password reset, and user session management.
 */
final class AuthService
{
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

        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
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

        $user_id = wp_create_user($command->userLogin, $command->userPassword, $command->userEmail);
        
        if (is_wp_error($user_id)) {
            return [
                'success' => false,
                'error' => $user_id->get_error_message()
            ];
        }

        // Auto-login the new user
        $user = get_user_by('id', $user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

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

        $result = retrieve_password($user->user_login);
        
        if (is_wp_error($result)) {
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
        wp_logout();
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?\WP_User
    {
        $user = wp_get_current_user();
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

        if (!is_email($command->userEmail)) {
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
        $user = get_user_by('login', $login);
        if (!$user) {
            $user = get_user_by('email', $login);
        }
        return $user ?: null;
    }
}