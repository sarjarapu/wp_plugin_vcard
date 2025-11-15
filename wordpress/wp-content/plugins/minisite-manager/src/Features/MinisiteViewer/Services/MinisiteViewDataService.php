<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Psr\Log\LoggerInterface;

/**
 * Minisite View Data Service
 *
 * SINGLE RESPONSIBILITY: Fetch and prepare data for minisite view rendering
 * - Fetches reviews for a minisite
 * - Checks if minisite is bookmarked by current user
 * - Checks if current user can edit the minisite
 * - Returns MinisiteViewModel with all view data
 *
 * This service separates data fetching from rendering logic.
 */
class MinisiteViewDataService
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-view-data');
    }

    /**
     * Prepare view model for minisite rendering
     *
     * Fetches all necessary data (reviews, bookmarks, permissions) and
     * returns a MinisiteViewModel ready for rendering.
     *
     * @param Minisite $minisite The minisite entity to prepare view data for
     * @return MinisiteViewModel View model with all data needed for rendering
     */
    public function prepareViewModel(Minisite $minisite): MinisiteViewModel
    {
        $reviews = $this->fetchReviews($minisite->id);
        $isBookmarked = $this->checkIfBookmarked($minisite->id);
        $canEdit = $this->checkIfCanEdit($minisite->id);

        return new MinisiteViewModel(
            minisite: $minisite,
            reviews: $reviews,
            isBookmarked: $isBookmarked,
            canEdit: $canEdit
        );
    }

    /**
     * Fetch approved reviews for a minisite
     *
     * @param string $minisiteId Minisite ID
     * @return array Array of review entities/arrays
     */
    protected function fetchReviews(string $minisiteId): array
    {
        // Use global ReviewRepository (initialized in PluginBootstrap)
        if (! isset($GLOBALS['minisite_review_repository'])) {
            $this->logger->warning('ReviewRepository not available, returning empty reviews', array(
                'minisite_id' => $minisiteId,
            ));

            return array();
        }

        try {
            /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepository $reviewRepo */
            $reviewRepo = $GLOBALS['minisite_review_repository'];

            return $reviewRepo->listApprovedForMinisite($minisiteId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch reviews', array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));

            return array();
        }
    }

    /**
     * Check if minisite is bookmarked by current user
     *
     * @param string $minisiteId Minisite ID
     * @return bool True if bookmarked, false otherwise
     */
    protected function checkIfBookmarked(string $minisiteId): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        try {
            global $wpdb;
            $userId = get_current_user_id();
            $bookmarkExists = db::get_var(
                "SELECT id FROM {$wpdb->prefix}minisite_bookmarks WHERE user_id = %d AND minisite_id = %d",
                array($userId, $minisiteId)
            );

            return (bool) $bookmarkExists;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check bookmark status', array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));

            return false;
        }
    }

    /**
     * Check if current user can edit the minisite
     *
     * @param string $minisiteId Minisite ID
     * @return bool True if user can edit, false otherwise
     */
    protected function checkIfCanEdit(string $minisiteId): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        try {
            return current_user_can('minisite_edit_profile', $minisiteId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check edit permission', array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
            ));

            return false;
        }
    }
}
