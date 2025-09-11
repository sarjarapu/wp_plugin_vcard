<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\ProfileRevision;

interface ProfileRevisionRepositoryInterface
{
    public function add(ProfileRevision $rev): ProfileRevision;
    /** @return ProfileRevision[] */
    public function listForProfile(int $profileId, int $limit = 20): array;
}
