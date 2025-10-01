<?php

namespace Minisite\Domain\Entities;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

final class Version
{
    public function __construct(
        public ?int $id,
        public string $minisiteId,
        public int $versionNumber,
        public string $status,        // draft|published
        public ?string $label,
        public ?string $comment,
        public int $createdBy,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $publishedAt,
        public ?int $sourceVersionId,  // For rollbacks: tracks what version was rolled back from
        public array $siteJson,  // Required - contains the form data
        // Minisite fields for complete versioning (all optional)
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
