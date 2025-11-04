<?php

namespace Minisite\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;

/**
 * ReviewHooks
 *
 * SINGLE RESPONSIBILITY: WordPress hook registration and routing for reviews
 * - Registers WordPress hooks
 * - Handles route interception (when review management UI is added)
 * - Delegates to controllers
 */
final class ReviewHooks
{
    public function __construct(
        private ReviewRepository $reviewRepository
    ) {
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void
    {
        // Placeholder for future review management hooks
        // When review management UI is added, routes and handlers will be registered here
    }
}

