<?php

namespace Minisite\Features\MinisiteDisplay;

use Minisite\Features\MinisiteDisplay\Hooks\DisplayHooksFactory;

/**
 * MinisiteDisplay Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the MinisiteDisplay feature
 * - Initializes the minisite display system
 * - Registers all display hooks
 * - Provides a clean entry point for the feature
 */
final class MinisiteDisplayFeature
{
    /**
     * Initialize the MinisiteDisplay feature
     */
    public static function initialize(): void
    {
        $displayHooks = DisplayHooksFactory::create();
        $displayHooks->register();
    }
}
