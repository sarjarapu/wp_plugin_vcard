<?php
namespace Minisite\Domain\Entities;

final class Version
{
    public function __construct(
        public ?int $id,
        public int $minisiteId,
        public int $versionNumber,
        public string $status,        // draft|published
        public ?string $label,
        public ?string $comment,
        public array $dataJson,
        public int $createdBy,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $publishedAt,
        public ?int $sourceVersionId  // For rollbacks: tracks what version was rolled back from
    ) {}

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRollback(): bool
    {
        return $this->sourceVersionId !== null;
    }
}
