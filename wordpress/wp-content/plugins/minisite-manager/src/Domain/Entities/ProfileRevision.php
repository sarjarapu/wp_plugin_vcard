<?php
namespace Minisite\Domain\Entities;

final class ProfileRevision
{
    public function __construct(
        public ?int $id,
        public int $profileId,
        public int $revisionNumber,
        public string $status,        // draft|published|archived
        public int $schemaVersion,
        public array $siteJson,
        public ?\DateTimeImmutable $createdAt,
        public ?int $createdBy
    ) {}
}