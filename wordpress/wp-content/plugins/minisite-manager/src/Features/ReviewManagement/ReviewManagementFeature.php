<?php

namespace Minisite\Features\ReviewManagement;

use Minisite\Features\ReviewManagement\Hooks\ReviewHooksFactory;

/**
 * ReviewManagement Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the ReviewManagement feature
 * - Initializes the review management system
 * - Registers all review-related hooks
 * - Provides a clean entry point for the feature
 */
final class ReviewManagementFeature
{
    /**
     * Initialize the ReviewManagement feature
     */
    public static function initialize(): void
    {
        // For now, just register hooks - no routes needed yet
        // When review management UI is added, hooks will be registered here
        // $reviewHooks = ReviewHooksFactory::create();
        // $reviewHooks->register();
    }
}
