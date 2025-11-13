<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;

interface MinisiteRepositoryInterface
{
    public function findBySlugs(SlugPair $slugs): ?Minisite;

    /**
     * Find minisite by ID
     */
    public function findById(string $id): ?Minisite;

    /**
     * Find minisite by individual slug parameters (for race condition checking)
     */
    public function findBySlugParams(string $businessSlug, string $locationSlug): ?Minisite;

    /**
     * Insert a new minisite
     */
    public function insert(Minisite $minisite): Minisite;

    /**
     * Save live row with optimistic locking.
     *
     * @throws \RuntimeException if version check fails
     */
    public function save(Minisite $minisite, int $expectedSiteVersion): Minisite;

    /**
     * Update the slug for a minisite (for draft creation)
     */
    public function updateSlug(string $id, string $slug): void;

    /**
     * Update business and location slugs for a minisite (for publishing)
     */
    public function updateSlugs(string $id, string $businessSlug, string $locationSlug): void;

    /**
     * Update the publish status for a minisite
     */
    public function updatePublishStatus(string $id, string $publishStatus): void;

    /**
     * Update the current version ID for a minisite
     */
    public function updateCurrentVersionId(string $id, int $versionId): void;

    /**
     * List minisites owned by a user
     *
     * @return Minisite[]
     */
    public function listByOwner(int $userId, int $limit = 50, int $offset = 0): array;

    /**
     * Count minisites by owner
     */
    public function countByOwner(int $userId): int;

    /**
     * Update multiple minisite fields in a single operation
     */
    public function updateMinisiteFields(string $minisiteId, array $fields, int $updatedBy): void;

    /**
     * Publish a minisite using the versioning system
     */
    public function publishMinisite(string $id): void;
}
