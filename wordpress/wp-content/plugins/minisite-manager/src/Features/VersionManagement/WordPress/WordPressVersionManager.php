<?php

namespace Minisite\Features\VersionManagement\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress-specific utilities for version management
 *
 * SINGLE RESPONSIBILITY: Version-specific WordPress operations
 * - AJAX response handling
 * - HTTP header management
 * - JSON encoding utilities
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressVersionManager extends BaseWordPressManager implements WordPressManagerInterface
{
    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

    // ===== VERSION-SPECIFIC METHODS ONLY =====

    /**
     * Send JSON success response
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    public function sendJsonSuccess(array $data = array(), int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send JSON error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return void
     */
    public function sendJsonError(string $message, int $statusCode = 400): void
    {
        wp_send_json_error($message, $statusCode);
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
     * Set no-cache headers
     *
     * @return void
     */
    public function setNoCacheHeaders(): void
    {
        nocache_headers();
    }

    /**
     * Encode data as JSON
     *
     * @param mixed $data Data to encode
     * @param int $options JSON encoding options
     * @param int $depth Maximum depth
     * @return string|false JSON string or false on failure
     */
    public function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return wp_json_encode($data, $options, $depth);
    }

    /**
     * Get home URL with optional scheme
     *
     * Override to support scheme parameter (not in base class).
     *
     * @param string $path Optional path to append
     * @param string|null $scheme Optional URL scheme
     * @return string Home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}
