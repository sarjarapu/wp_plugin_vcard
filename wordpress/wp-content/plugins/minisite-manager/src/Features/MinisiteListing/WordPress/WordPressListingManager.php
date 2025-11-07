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

    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        return wp_get_current_user();
    }

    /**
     * Check if current user has capability
     */
    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Get home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }

    /**
     * Redirect to URL
     * Uses base class redirect() method which handles termination
     */
    public function redirect(string $location, int $status = 302): void
    {
        parent::redirect($location, $status);
    }

}
