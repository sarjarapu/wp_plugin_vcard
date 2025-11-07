<?php

declare(strict_types=1);

namespace Minisite\Features\VersionManagement\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;

/**
 * Version Entity - Doctrine ORM
 *
 * Represents a version of a minisite (draft or published).
 * Complete versioning system that stores all minisite fields for each version.
 *
 * ⚠️ CRITICAL: location_point is handled via raw SQL in the repository.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 */
#[ORM\Entity(repositoryClass: \Minisite\Features\VersionManagement\Repositories\VersionRepository::class)]
#[ORM\Table(name: 'minisite_versions')]
#[ORM\UniqueConstraint(name: 'uniq_minisite_version', columns: array('minisite_id', 'version_number'))]
#[ORM\Index(name: 'idx_minisite_status', columns: array('minisite_id', 'status'))]
#[ORM\Index(name: 'idx_minisite_created', columns: array('minisite_id', 'created_at'))]
final class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: array('unsigned' => true))]
    public ?int $id = null;

    #[ORM\Column(name: 'minisite_id', type: 'string', length: 32)]
    public string $minisiteId;

    #[ORM\Column(name: 'version_number', type: 'integer', options: array('unsigned' => true))]
    public int $versionNumber;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status; // 'draft'|'published'

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    public ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $comment = null;

    #[ORM\Column(name: 'created_by', type: 'bigint', options: array('unsigned' => true))]
    public int $createdBy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'source_version_id', type: 'bigint', nullable: true, options: array('unsigned' => true))]
    public ?int $sourceVersionId = null; // For rollbacks: tracks what version was rolled back from

    #[ORM\Column(name: 'business_slug', type: 'string', length: 120, nullable: true)]
    public ?string $businessSlug = null;

    #[ORM\Column(name: 'location_slug', type: 'string', length: 120, nullable: true)]
    public ?string $locationSlug = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    public ?string $name = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    public ?string $city = null;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    public ?string $region = null;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2, nullable: true)]
    public ?string $countryCode = null;

    #[ORM\Column(name: 'postal_code', type: 'string', length: 20, nullable: true)]
    public ?string $postalCode = null;

    /**
     * ⚠️ CRITICAL: location_point is NOT mapped as a Doctrine column.
     * It is handled via raw SQL in the repository (save/mapRow methods).
     * DO NOT modify the location_point handling logic.
     * See: docs/issues/location-point-lessons-learned.md
     *
     * This property is a convenience wrapper around the location_point column.
     * The repository handles conversion between GeoPoint and POINT geometry.
     */
    // location_point is handled via raw SQL - not mapped here

    #[ORM\Column(name: 'site_template', type: 'string', length: 32, nullable: true)]
    public ?string $siteTemplate = null;

    #[ORM\Column(type: 'string', length: 24, nullable: true)]
    public ?string $palette = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    public ?string $industry = null;

    #[ORM\Column(name: 'default_locale', type: 'string', length: 10, nullable: true)]
    public ?string $defaultLocale = null;

    #[ORM\Column(name: 'schema_version', type: 'smallint', nullable: true, options: array('unsigned' => true))]
    public ?int $schemaVersion = null;

    #[ORM\Column(name: 'site_version', type: 'integer', nullable: true, options: array('unsigned' => true))]
    public ?int $siteVersion = null;

    #[ORM\Column(name: 'site_json', type: 'text')]
    public string $siteJson; // JSON string - contains the form data

    #[ORM\Column(name: 'search_terms', type: 'text', nullable: true)]
    public ?string $searchTerms = null;

    /**
     * GeoPoint value object (not persisted directly - handled via location_point column)
     * This is a convenience property that the repository will sync with location_point.
     */
    public ?GeoPoint $geo = null;

    /**
     * SlugPair value object (convenience wrapper around business_slug and location_slug)
     * Not persisted directly - these are individual columns.
     */
    public ?SlugPair $slugs = null;

    /**
     * Constructor - supports both Doctrine hydration and programmatic creation
     *
     * For Doctrine: Properties are populated directly by Doctrine (default constructor)
     * For programmatic use: Can pass parameters to initialize the entity
     */
    public function __construct(
        ?int $id = null,
        ?string $minisiteId = null,
        ?int $versionNumber = null,
        ?string $status = null,
        ?string $label = null,
        ?string $comment = null,
        ?int $createdBy = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $publishedAt = null,
        ?int $sourceVersionId = null,
        ?array $siteJson = null,
        ?SlugPair $slugs = null,
        ?string $title = null,
        ?string $name = null,
        ?string $city = null,
        ?string $region = null,
        ?string $countryCode = null,
        ?string $postalCode = null,
        ?GeoPoint $geo = null,
        ?string $siteTemplate = null,
        ?string $palette = null,
        ?string $industry = null,
        ?string $defaultLocale = null,
        ?int $schemaVersion = null,
        ?int $siteVersion = null,
        ?string $searchTerms = null
    ) {
        // If parameters are provided, initialize the entity (backward compatibility)
        if ($minisiteId !== null) {
            $this->id = $id;
            $this->minisiteId = $minisiteId;
            $this->versionNumber = $versionNumber ?? 1;
            $this->status = $status ?? 'draft';
            $this->label = $label;
            $this->comment = $comment;
            $this->createdBy = $createdBy ?? 0;
            $this->createdAt = $createdAt ?? new \DateTimeImmutable();
            $this->publishedAt = $publishedAt;
            $this->sourceVersionId = $sourceVersionId;
            // siteJson: Accept array (for backward compatibility) or string (for Doctrine)
            // Use standard PHP json_encode() instead of wp_json_encode() for testability
            if ($siteJson !== null) {
                $this->siteJson = is_array($siteJson) ? json_encode($siteJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $siteJson;
            } else {
                $this->siteJson = '{}';
            }
            $this->slugs = $slugs;
            $this->businessSlug = $slugs?->business;
            $this->locationSlug = $slugs?->location;
            $this->title = $title;
            $this->name = $name;
            $this->city = $city;
            $this->region = $region;
            $this->countryCode = $countryCode;
            $this->postalCode = $postalCode;
            $this->geo = $geo;
            $this->siteTemplate = $siteTemplate;
            $this->palette = $palette;
            $this->industry = $industry;
            $this->defaultLocale = $defaultLocale;
            $this->schemaVersion = $schemaVersion;
            $this->siteVersion = $siteVersion;
            $this->searchTerms = $searchTerms;
        }
        // If no parameters, Doctrine will populate properties directly
    }

    /**
     * Get siteJson as array (decoded from JSON string)
     */
    public function getSiteJsonAsArray(): array
    {
        $decoded = json_decode($this->siteJson, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Set siteJson from array (encodes to JSON string)
     * Uses standard PHP json_encode() instead of wp_json_encode() for testability
     */
    public function setSiteJsonFromArray(array $data): void
    {
        $this->siteJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get SlugPair from business_slug and location_slug
     */
    public function getSlugs(): ?SlugPair
    {
        if ($this->slugs !== null) {
            return $this->slugs;
        }

        if ($this->businessSlug !== null && $this->locationSlug !== null) {
            return new SlugPair(
                business: $this->businessSlug,
                location: $this->locationSlug
            );
        }

        return null;
    }

    /**
     * Set slugs and update business_slug/location_slug
     */
    public function setSlugs(?SlugPair $slugs): void
    {
        $this->slugs = $slugs;
        $this->businessSlug = $slugs?->business;
        $this->locationSlug = $slugs?->location;
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
