<?php

namespace Minisite\Features\BaseFeature\Hooks;

use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Base Hook Abstract Class
 *
 * SINGLE RESPONSIBILITY: Provide common termination handling for all hooks
 * - Manages termination handler injection
 * - Provides consistent termination behavior
 * - Centralizes exit logic in one place
 *
 * All feature hooks should extend this class to ensure consistent
 * termination handling and reduce exit calls to a single location.
 */
abstract class BaseHook
{
    /**
     * Termination handler for preventing WordPress from loading default templates
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
     * Terminate script execution after handling route
     *
     * This prevents WordPress from loading default templates after we've handled
     * a custom route. In production, this calls exit(). In tests, it's a no-op.
     *
     * @return void
     */
    protected function terminate(): void
    {
        $this->terminationHandler->terminate();
    }
}
