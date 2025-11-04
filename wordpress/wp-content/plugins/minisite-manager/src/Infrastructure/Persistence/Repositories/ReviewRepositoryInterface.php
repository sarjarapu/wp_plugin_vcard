<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Review;

interface ReviewRepositoryInterface
{
    /**
     * Save review (insert or update)
     */
    public function save(Review $review): Review;

    /**
     * Find review by ID (type-safe)
     */
    public function findById(int $id): ?Review;

    /**
     * Find review by ID, throw if not found
     */
    public function findOrFail(int $id): Review;

    /**
     * Delete review
     */
    public function delete(Review $review): void;

    /**
     * List approved reviews for a minisite
     *
     * @param string $minisiteId
     * @param int $limit
     * @return Review[]
     */
    public function listApprovedForMinisite(string $minisiteId, int $limit = 20): array;

    /**
     * List reviews by status for a minisite
     *
     * @param string $minisiteId
     * @param string $status 'pending'|'approved'|'rejected'|'flagged'
     * @param int $limit
     * @return Review[]
     */
    public function listByStatusForMinisite(string $minisiteId, string $status, int $limit = 20): array;

    /**
     * Count reviews by status for a minisite
     */
    public function countByStatusForMinisite(string $minisiteId, string $status): int;
}
