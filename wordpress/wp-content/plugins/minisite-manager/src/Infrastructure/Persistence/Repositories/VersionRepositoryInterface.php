<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Version;

interface VersionRepositoryInterface
{
    public function save(Version $version): Version;
    public function findById(int $id): ?Version;
    public function findByMinisiteId(int $minisiteId, int $limit = 50, int $offset = 0): array;
    public function findLatestDraft(int $minisiteId): ?Version;
    public function findPublishedVersion(int $minisiteId): ?Version;
    public function getNextVersionNumber(int $minisiteId): int;
    public function delete(int $id): bool;
}
