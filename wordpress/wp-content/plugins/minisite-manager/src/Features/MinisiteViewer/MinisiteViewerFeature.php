<?php

namespace Minisite\Features\MinisiteViewer;

use Minisite\Features\MinisiteViewer\Hooks\ViewHooksFactory;

/**
 * MinisiteViewer Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the MinisiteViewer feature
 * - Initializes the minisite viewing system
 * - Registers all display hooks
 * - Provides a clean entry point for the feature
 */
final class MinisiteViewerFeature
{
    /**
     * Initialize the MinisiteViewer feature
     */
    public static function initialize(): void
    {
        $viewHooks = ViewHooksFactory::create();
        $viewHooks->register();
    }
}
