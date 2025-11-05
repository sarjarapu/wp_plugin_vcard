<?php

namespace Minisite\Features\BaseFeature\WordPress;

use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Base WordPress Manager Abstract Class
 *
 * SINGLE RESPONSIBILITY: Provide common redirect handling for all WordPress managers
 * - Manages termination handler injection
 * - Provides consistent redirect behavior with termination
 * - Centralizes redirect + exit logic in one place
 *
 * All WordPress managers should extend this class to ensure consistent
 * redirect handling and reduce exit calls to a single location.
 */
abstract class BaseWordPressManager
{
    /**
     * Termination handler for preventing WordPress from loading default templates after redirect
     */
    protected TerminationHandlerInterface $terminationHandler;

    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        $this->terminationHandler = $terminationHandler;
    }

    /**
     * Redirect to URL and terminate script execution
     *
     * This follows WordPress best practice: wp_redirect() + exit.
     * In production, this calls exit() after redirect. In tests, it's a no-op.
     *
     * @param string $url URL to redirect to
     * @param int $status HTTP status code (default: 302)
     * @return void
     */
    public function redirect(string $url, int $status = 302): void
    {
        wp_redirect($url, $status);
        // Terminate after redirect (inherited termination handler)
        // In production: calls exit(). In tests: no-op, allowing tests to continue
        $this->terminationHandler->terminate();
    }
}
