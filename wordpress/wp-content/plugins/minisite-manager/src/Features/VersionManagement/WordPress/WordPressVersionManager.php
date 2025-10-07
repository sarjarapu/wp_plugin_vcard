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

    /**
     * Send JSON success response
     */
    public function sendJsonSuccess(array $data = [], int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public function sendJsonError(string $message, int $statusCode = 400): void
    {
        wp_send_json_error($message, $statusCode);
    }

    /**
     * Redirect to URL
     */
    public function redirect(string $location, int $status = 302): void
    {
        wp_redirect($location, $status);
    }

    /**
     * Set HTTP status header
     */
    public function setStatusHeader(int $code): void
    {
        status_header($code);
    }

    /**
     * Set no-cache headers
     */
    public function setNoCacheHeaders(): void
    {
        nocache_headers();
    }

    /**
     * Encode data as JSON
     */
    public function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return wp_json_encode($data, $options, $depth);
    }
}
