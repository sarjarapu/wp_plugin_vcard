<?php

declare(strict_types=1);

namespace Minisite\Features\ReviewManagement\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress Review Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific review operations
 * - Manages WordPress database interactions for reviews
 * - Provides clean interface for WordPress functions
 * - Handles user authentication and authorization
 *
 * All common WordPress operations are inherited from BaseWordPressManager.
 */
class WordPressReviewManager extends BaseWordPressManager implements WordPressManagerInterface
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

    // ===== REVIEW-SPECIFIC METHODS ONLY =====
    // Add any review-specific WordPress operations here
    // All common methods inherited from BaseWordPressManager
}

