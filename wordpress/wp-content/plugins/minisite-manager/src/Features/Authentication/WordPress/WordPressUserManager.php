<?php

declare(strict_types=1);

namespace Minisite\Features\Authentication\WordPress;

/**
 * WordPress User Manager
 *
 * Wraps WordPress user-related functions for better testability and dependency injection.
 * This class provides a clean interface to WordPress user management functions,
 * allowing us to mock them easily in tests.
 */
final class WordPressUserManager
{
    /**
     * Authenticate a user with username/password
     *
     * @param array $credentials User credentials
     * @param bool $secure_cookie Whether to use secure cookie
     * @return \WP_User|\WP_Error User object on success, WP_Error on failure
     */
    public function signon(array $credentials, bool $secure_cookie = false)
    {
        return wp_signon($credentials, $secure_cookie);
    }

    /**
     * Create a new user
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $email Email address
     * @return int|\WP_Error User ID on success, WP_Error on failure
     */
    public function createUser(string $username, string $password, string $email = '')
    {
        return wp_create_user($username, $password, $email);
    }

    /**
     * Get user by field
     *
     * @param string $field Field to search by (id, slug, email, login)
     * @param string|int $value Value to search for
     * @return \WP_User|false User object on success, false on failure
     */
    public function getUserBy(string $field, $value)
    {
        return get_user_by($field, $value);
    }

    /**
     * Get current user
     *
     * @return \WP_User Current user object
     */
    public function getCurrentUser()
    {
        return wp_get_current_user();
    }

    /**
     * Set current user
     *
     * @param int $user_id User ID
     * @return \WP_User User object
     */
    public function setCurrentUser(int $user_id)
    {
        return wp_set_current_user($user_id);
    }

    /**
     * Set authentication cookie
     *
     * @param int $user_id User ID
     * @param bool $remember Whether to remember the user
     * @param bool $secure Whether to use secure cookie
     * @return void
     */
    public function setAuthCookie(int $user_id, bool $remember = false, bool $secure = false): void
    {
        wp_set_auth_cookie($user_id, $remember, $secure);
    }

    /**
     * Log out current user
     *
     * @return void
     */
    public function logout(): void
    {
        wp_logout();
    }

    /**
     * Check if user is logged in
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check if value is WP_Error
     *
     * @param mixed $thing Value to check
     * @return bool True if WP_Error, false otherwise
     */
    public function isWpError($thing): bool
    {
        return is_wp_error($thing);
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool True if valid email, false otherwise
     */
    public function isEmail(string $email): bool
    {
        return is_email($email);
    }

    /**
     * Sanitize text field
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    public function sanitizeText(string $text): string
    {
        return sanitize_text_field($text);
    }

    /**
     * Sanitize email
     *
     * @param string $email Email to sanitize
     * @return string Sanitized email
     */
    public function sanitizeEmail(string $email): string
    {
        return sanitize_email($email);
    }

    /**
     * Sanitize URL
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    public function sanitizeUrl(string $url): string
    {
        return sanitize_url($url);
    }

    /**
     * Remove slashes from string
     *
     * @param string $string String to unslash
     * @return string Unslashed string
     */
    public function unslash(string $string): string
    {
        return wp_unslash($string);
    }

    /**
     * Verify nonce
     *
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool|int False on failure, 1 or 2 on success
     */
    public function verifyNonce(string $nonce, string $action = '-1')
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Redirect to URL
     *
     * @param string $location URL to redirect to
     * @param int $status HTTP status code
     * @return void
     */
    public function redirect(string $location, int $status = 302): void
    {
        wp_redirect($location, $status);
    }

    /**
     * Get home URL
     *
     * @param string $path Optional path to append
     * @return string Home URL
     */
    public function getHomeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    /**
     * Get query variable
     *
     * @param string $var Variable name
     * @param mixed $default Default value
     * @return mixed Query variable value
     */
    public function getQueryVar(string $var, $default = '')
    {
        return get_query_var($var, $default);
    }

    /**
     * Set HTTP status header
     *
     * @param int $code HTTP status code
     * @return void
     */
    public function setStatusHeader(int $code): void
    {
        status_header($code);
    }

    /**
     * Load template part
     *
     * @param string $slug Template slug
     * @param string $name Template name
     * @return void
     */
    public function getTemplatePart(string $slug, string $name = ''): void
    {
        get_template_part($slug, $name);
    }

    /**
     * Get global WP_Query object
     *
     * @return \WP_Query|null Global query object
     */
    public function getWpQuery(): ?\WP_Query
    {
        global $wp_query;
        return $wp_query;
    }

    /**
     * Retrieve password for user
     *
     * @param string $user_login User login
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function retrievePassword(string $user_login)
    {
        return retrieve_password($user_login);
    }
}
