<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteViewer\ViewModels;

use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;

/**
 * Minisite View Model
 *
 * DTO (Data Transfer Object) for minisite view rendering.
 * Contains all data needed for rendering a minisite view:
 * - Minisite entity
 * - Reviews
 * - User-specific flags (isBookmarked, canEdit)
 *
 * This separates data fetching from rendering logic.
 */
class MinisiteViewModel
{
    /**
     * @param Minisite $minisite The minisite entity
     * @param array $reviews Array of review entities/arrays
     * @param bool $isBookmarked Whether the current user has bookmarked this minisite
     * @param bool $canEdit Whether the current user can edit this minisite
     */
    public function __construct(
        public readonly Minisite $minisite,
        public readonly array $reviews = array(),
        public readonly bool $isBookmarked = false,
        public readonly bool $canEdit = false
    ) {
    }

    /**
     * Get minisite entity
     */
    public function getMinisite(): Minisite
    {
        return $this->minisite;
    }

    /**
     * Get reviews array
     */
    public function getReviews(): array
    {
        return $this->reviews;
    }

    /**
     * Check if minisite is bookmarked by current user
     */
    public function isBookmarked(): bool
    {
        return $this->isBookmarked;
    }

    /**
     * Check if current user can edit this minisite
     */
    public function canEdit(): bool
    {
        return $this->canEdit;
    }

    /**
     * Convert to array for template rendering
     *
     * Sets user-specific properties (isBookmarked, canEdit) on the minisite entity
     * for template access. These properties are not persisted to the database.
     *
     * @return array{minisite: Minisite, reviews: array}
     */
    public function toArray(): array
    {
        // Set user-specific properties on minisite entity for template access
        $minisite = $this->minisite;
        $minisite->isBookmarked = $this->isBookmarked;
        $minisite->canEdit = $this->canEdit;

        return array(
            'minisite' => $minisite,
            'reviews' => $this->reviews,
        );
    }
}
