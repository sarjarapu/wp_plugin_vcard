<?php

namespace Minisite\Domain\Entities;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

class Minisite
{
    public function __construct(
        public string $id,
        public ?string $slug,
        public SlugPair $slugs,
        public string $title,
        public string $name,
        public string $city,
        public ?string $region,
        public string $countryCode,
        public ?string $postalCode,
        public ?GeoPoint $geo,
        public string $siteTemplate,   // e.g. v2025
        public string $palette,        // e.g. blue
        public string $industry,       // e.g. services
        public string $defaultLocale,  // e.g. en-US
        public int $schemaVersion,     // schema version of JSON payload
        public int $siteVersion,       // optimistic lock / live version
        public array $siteJson,        // denormalized site data for rendering
        public ?string $searchTerms,   // normalized searchable text
        public string $status,         // draft|published|archived
        public string $publishStatus,  // draft|reserved|published
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $publishedAt,
        public ?int $createdBy,
        public ?int $updatedBy,
        public ?int $currentVersionId,  // Points to currently published version
        public bool $isBookmarked = false,  // Whether current user has bookmarked this minisite
        public bool $canEdit = false        // Whether current user can edit this minisite
    ) {
    }
}
