<?php

namespace Minisite\Features\MinisiteViewer\WordPress;

use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific minisite operations
 * - Manages minisite data retrieval
 * - Handles WordPress database interactions
 * - Provides clean interface for minisite operations
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressMinisiteManager extends BaseWordPressManager
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

    // ===== MINISITEVIEWER-SPECIFIC METHODS ONLY =====

    /**
     * Get login redirect URL
     *
     * @return string
     */
    public function getLoginRedirectUrl(): string
    {
        return wp_login_url();
    }

    // ===== VERSION-SPECIFIC METHODS =====

    /**
     * Get reviews for a minisite
     *
     * @param string $minisiteId
     * @return array
     */
    public function getReviewsForMinisite(string $minisiteId): array
    {
        // Use global ReviewRepository (initialized in PluginBootstrap)
        if (! isset($GLOBALS['minisite_review_repository'])) {
            return array();
        }

        /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepository $reviewRepo */
        $reviewRepo = $GLOBALS['minisite_review_repository'];

        return $reviewRepo->listApprovedForMinisite($minisiteId);
    }
}
