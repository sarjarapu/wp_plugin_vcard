<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;

interface MinisiteRepositoryInterface
{
    public function findBySlugs(SlugPair $slugs): ?Minisite;

    /**
     * Find minisite by ID
     */
    public function findById(string $id): ?Minisite;

    /**
     * Save live row with optimistic locking.
     * @throws \RuntimeException if version check fails
     */
    public function save(Minisite $minisite, int $expectedSiteVersion): Minisite;
}
