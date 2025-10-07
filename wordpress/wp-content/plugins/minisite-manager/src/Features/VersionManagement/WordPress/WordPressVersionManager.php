<?php

namespace Minisite\Features\VersionManagement\WordPress;

/**
 * WordPress-specific utilities for version management
 */
class WordPressVersionManager
{
    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?\WP_User
    {
        return wp_get_current_user();
    }

    /**
     * Verify nonce
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Get query variable
     */
    public function getQueryVar(string $var, mixed $default = ''): mixed
    {
        return get_query_var($var, $default);
    }

    /**
     * Sanitize text field
     */
    public function sanitizeTextField(string $str): string
    {
        return sanitize_text_field($str);
    }

    /**
     * Sanitize textarea field
     */
    public function sanitizeTextareaField(string $str): string
    {
        return sanitize_textarea_field($str);
    }

    /**
     * Escape URL
     */
    public function escUrlRaw(string $url): string
    {
        return esc_url_raw($url);
    }

    /**
     * Get home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }

    /**
     * Remove slashes from string
     */
    public function unslash(string $string): string
    {
        return wp_unslash($string);
    }
}
