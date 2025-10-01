<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Version;

interface VersionRepositoryInterface
{
    public function save(Version $version): Version;
    public function findById(int $id): ?Version;
    public function findByMinisiteId(string $minisiteId, int $limit = 50, int $offset = 0): array;
    public function findLatestVersion(string $minisiteId): ?Version;
    public function findLatestDraft(string $minisiteId): ?Version;
    public function findPublishedVersion(string $minisiteId): ?Version;
    public function getNextVersionNumber(string $minisiteId): int;
    public function delete(int $id): bool;
}
