<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteListing\WordPress;

use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress Listing Manager
 *
 * SINGLE RESPONSIBILITY: Provide WordPress-specific utilities for listing functionality
 * - Handles listing minisites by owner
 * - Provides WordPress-specific data formatting
 * - Acts as a bridge between the listing service and WordPress
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressListingManager extends BaseWordPressManager
{
    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

    // ===== LISTING-SPECIFIC METHODS ONLY =====

    /**
     * Check if current user has capability
     *
     * @param string $capability Capability to check
     * @return bool True if user has capability
     */
    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Get home URL with optional scheme
     *
     * Override to support scheme parameter (not in base class).
     *
     * @param string $path Optional path to append
     * @param string|null $scheme Optional URL scheme
     * @return string Home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}
