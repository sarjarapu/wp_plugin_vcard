<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Profile;
use Minisite\Domain\ValueObjects\SlugPair;

interface ProfileRepositoryInterface
{
    public function findBySlugs(SlugPair $slugs): ?Profile;

    /**
     * Find profile by ID
     */
    public function findById(string $id): ?Profile;

    /**
     * Save live row with optimistic locking.
     * @throws \RuntimeException if version check fails
     */
    public function save(Profile $profile, int $expectedSiteVersion): Profile;
}