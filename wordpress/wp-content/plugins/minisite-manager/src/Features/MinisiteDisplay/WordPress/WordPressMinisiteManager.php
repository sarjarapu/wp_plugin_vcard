<?php

namespace Minisite\Features\MinisiteDisplay\WordPress;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific minisite operations
 * - Manages minisite data retrieval
 * - Handles WordPress database interactions
 * - Provides clean interface for minisite operations
 */
final class WordPressMinisiteManager
{
    private ?MinisiteRepository $repository = null;

    /**
     * Get minisite repository instance
     */
    private function getRepository(): MinisiteRepository
    {
        if ($this->repository === null) {
            $this->repository = new MinisiteRepository(db::getWpdb());
        }
        return $this->repository;
    }

    /**
     * Find minisite by business and location slugs
     *
     * @param string $businessSlug
     * @param string $locationSlug
     * @return object|null
     */
    public function findMinisiteBySlugs(string $businessSlug, string $locationSlug): ?object
    {
        $slugPair = new SlugPair($businessSlug, $locationSlug);
        return $this->getRepository()->findBySlugs($slugPair);
    }

    /**
     * Check if minisite exists
     *
     * @param string $businessSlug
     * @param string $locationSlug
     * @return bool
     */
    public function minisiteExists(string $businessSlug, string $locationSlug): bool
    {
        return $this->findMinisiteBySlugs($businessSlug, $locationSlug) !== null;
    }
}
