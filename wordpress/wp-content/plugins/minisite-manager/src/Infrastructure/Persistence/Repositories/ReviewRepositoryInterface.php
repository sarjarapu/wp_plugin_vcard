<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Review;

interface ReviewRepositoryInterface
{
    public function add(Review $review): Review;
    /** @return Review[] */
    public function listApprovedForProfile(int $minisiteId, int $limit = 20): array;
}