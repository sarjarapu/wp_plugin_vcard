<?php

namespace Minisite\Features\MinisiteEdit;

use Minisite\Features\MinisiteEdit\Hooks\EditHooksFactory;

/**
 * MinisiteEdit Feature Bootstrap
 *
 * SINGLE RESPONSIBILITY: Bootstrap the MinisiteEdit feature
 * - Initializes the minisite editing system
 * - Registers all editing hooks
 * - Provides a clean entry point for the feature
 */
final class MinisiteEditFeature
{
    /**
     * Initialize the MinisiteEdit feature
     */
    public static function initialize(): void
    {
        $editHooks = EditHooksFactory::create();
        $editHooks->register();

        // Register template_redirect handler with priority 3 to run before VersionManagementFeature (priority 5)
        add_action('template_redirect', [$editHooks, 'handleEditRoutes'], 3);
    }
}
