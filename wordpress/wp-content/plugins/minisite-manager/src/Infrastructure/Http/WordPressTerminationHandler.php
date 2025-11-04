<?php

namespace Minisite\Infrastructure\Http;

/**
 * Production implementation of TerminationHandler that calls exit()
 *
 * This prevents WordPress from loading default templates after we've rendered
 * our custom output or performed a redirect.
 */
final class WordPressTerminationHandler implements TerminationHandlerInterface
{
    /**
     * Terminate script execution
     */
    public function terminate(): void
    {
        exit;
    }
}

