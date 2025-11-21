<?php

namespace Minisite\Features\MinisiteEdit\WordPress;

use Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress Edit Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific edit operations
 * - Manages WordPress database interactions for editing
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressEditManager extends BaseWordPressManager implements WordPressManagerInterface
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

    // ===== EDIT-SPECIFIC METHODS ONLY =====

    /**
     * Get login redirect URL
     */
    public function getLoginRedirectUrl(): string
    {
        $currentUrl = isset($_SERVER['REQUEST_URI']) ?
            sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        return $this->getHomeUrl('/account/login?redirect_to=' . urlencode($currentUrl));
    }

    /**
     * Check if user owns minisite
     */
    public function userOwnsMinisite(object $minisite, int $userId): bool
    {
        return $minisite->createdBy === $userId;
    }
}
