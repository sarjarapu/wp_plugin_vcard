<?php

namespace Minisite\Infrastructure\Http;

/**
 * Test implementation of TerminationHandler that does nothing
 *
 * This allows controllers to be tested without actually terminating the PHP process.
 */
final class TestTerminationHandler implements TerminationHandlerInterface
{
    /**
     * No-op in tests - allows test execution to continue
     */
    public function terminate(): void
    {
        // Intentionally do nothing - tests should continue execution
    }
}

