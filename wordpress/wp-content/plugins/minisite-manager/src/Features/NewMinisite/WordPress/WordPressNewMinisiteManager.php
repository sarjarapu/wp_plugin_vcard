<?php

namespace Minisite\Features\NewMinisite\WordPress;

use Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress New Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific new minisite operations
 * - Manages WordPress database interactions for new minisite creation
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressNewMinisiteManager extends BaseWordPressManager implements WordPressManagerInterface
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

    // ===== NEWMINISITE-SPECIFIC METHODS ONLY =====

    /**
     * Get login redirect URL
     */
    public function getLoginRedirectUrl(): string
    {
        return wp_login_url($this->getHomeUrl('/account/sites/new'));
    }

    /**
     * Check if user can create new minisites
     */
    public function userCanCreateMinisite(int $userId): bool
    {
        // For now, allow all logged-in users to create minisites
        // This can be extended with subscription checks, limits, etc.
        return user_can($userId, 'read');
    }
}
