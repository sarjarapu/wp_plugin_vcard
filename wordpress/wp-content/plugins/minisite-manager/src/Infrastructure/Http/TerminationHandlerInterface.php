<?php

namespace Minisite\Infrastructure\Http;

/**
 * Interface for handling script termination after rendering or redirecting
 *
 * This abstraction allows controllers to be testable by injecting a mock
 * termination handler in tests, while using a real exit() implementation in production.
 */
interface TerminationHandlerInterface
{
    /**
     * Terminate script execution after rendering output or redirecting
     *
     * In production, this calls exit() to prevent WordPress from loading default templates.
     * In tests, this can be a no-op or throw a test exception.
     */
    public function terminate(): void;
}
