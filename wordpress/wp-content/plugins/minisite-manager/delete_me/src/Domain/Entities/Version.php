<?php

namespace delete_me\Minisite\Domain\Entities;

// Legacy Version entity retained for backwards compatibility during cleanup.
// Use Minisite\Features\VersionManagement\Domain\Entities\Version instead.

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;

class Version
{
    public function __construct(
        public ?int $id,
        public string $minisiteId,
        public int $versionNumber,
        public string $status,
        public ?string $label,
        public ?string $comment,
        public int $createdBy,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $publishedAt,
        public ?int $sourceVersionId,
        public array $siteJson,
        public ?SlugPair $slugs = null,
        public ?string $title = null,
        public ?string $name = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $countryCode = null,
        public ?string $postalCode = null,
        public ?GeoPoint $geo = null,
        public ?string $siteTemplate = null,
        public ?string $palette = null,
        public ?string $industry = null,
        public ?string $defaultLocale = null,
        public ?int $schemaVersion = null,
        public ?int $siteVersion = null,
        public ?string $searchTerms = null
    ) {
    }

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
