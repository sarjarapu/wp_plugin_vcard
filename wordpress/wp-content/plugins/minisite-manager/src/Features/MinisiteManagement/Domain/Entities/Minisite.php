<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteManagement\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;

/**
 * Minisite Entity - Doctrine ORM
 *
 * Represents a minisite (business microsite).
 *
 * ⚠️ CRITICAL: location_point is handled via raw SQL in the repository.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 */
#[ORM\Entity(repositoryClass: \Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository::class)]
#[ORM\Table(name: 'minisites')]
#[ORM\UniqueConstraint(name: 'uniq_slug', columns: array('slug'))]
#[ORM\UniqueConstraint(name: 'uniq_business_location', columns: array('business_slug', 'location_slug'))]
class Minisite
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    public string $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $slug = null;

    #[ORM\Column(name: 'business_slug', type: 'string', length: 120, nullable: true)]
    public ?string $businessSlug = null;

    #[ORM\Column(name: 'location_slug', type: 'string', length: 120, nullable: true)]
    public ?string $locationSlug = null;

    #[ORM\Column(type: 'string', length: 200)]
    public string $title;

    #[ORM\Column(type: 'string', length: 200)]
    public string $name;

    #[ORM\Column(type: 'string', length: 120)]
    public string $city;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    public ?string $region = null;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    public string $countryCode;

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

    #[ORM\Column(name: 'site_template', type: 'string', length: 32)]
    public string $siteTemplate; // e.g. v2025

    #[ORM\Column(type: 'string', length: 24)]
    public string $palette; // e.g. blue

    #[ORM\Column(type: 'string', length: 40)]
    public string $industry; // e.g. services

    #[ORM\Column(name: 'default_locale', type: 'string', length: 10)]
    public string $defaultLocale; // e.g. en-US

    #[ORM\Column(name: 'schema_version', type: 'smallint', options: array('unsigned' => true))]
    public int $schemaVersion; // schema version of JSON payload

    #[ORM\Column(name: 'site_version', type: 'integer', options: array('unsigned' => true))]
    public int $siteVersion; // optimistic lock / live version

    #[ORM\Column(name: 'site_json', type: 'text')]
    public string $siteJson; // JSON string - contains the form data

    #[ORM\Column(name: 'search_terms', type: 'text', nullable: true)]
    public ?string $searchTerms = null; // normalized searchable text

    #[ORM\Column(type: 'string', length: 20)]
    public string $status; // 'draft'|'published'|'archived'

    #[ORM\Column(name: 'publish_status', type: 'string', length: 20)]
    public string $publishStatus; // 'draft'|'reserved'|'published'

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'created_by', type: 'bigint', nullable: true, options: array('unsigned' => true))]
    public ?int $createdBy = null;

    #[ORM\Column(name: 'updated_by', type: 'bigint', nullable: true, options: array('unsigned' => true))]
    public ?int $updatedBy = null;

    #[ORM\Column(
        name: '_minisite_current_version_id',
        type: 'bigint',
        nullable: true,
        options: array('unsigned' => true)
    )]
    public ?int $currentVersionId = null; // Points to currently published version

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
     * Whether current user has bookmarked this minisite (runtime property, not persisted)
     */
    public bool $isBookmarked = false;

    /**
     * Whether current user can edit this minisite (runtime property, not persisted)
     */
    public bool $canEdit = false;

    /**
     * Constructor - supports both Doctrine hydration and programmatic creation
     *
     * For Doctrine: Properties are populated directly by Doctrine (default constructor)
     * For programmatic use: Can pass parameters to initialize the entity
     */
    public function __construct(
        ?string $id = null,
        ?string $slug = null,
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
        ?array $siteJson = null,
        ?string $searchTerms = null,
        ?string $status = null,
        ?string $publishStatus = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $publishedAt = null,
        ?int $createdBy = null,
        ?int $updatedBy = null,
        ?int $currentVersionId = null
    ) {
        // If parameters are provided, initialize the entity (backward compatibility)
        if ($id !== null) {
            $this->id = $id;
            $this->slug = $slug;
            $this->slugs = $slugs;
            $this->businessSlug = $slugs?->business;
            $this->locationSlug = $slugs?->location;
            $this->title = $title ?? '';
            $this->name = $name ?? '';
            $this->city = $city ?? '';
            $this->region = $region;
            $this->countryCode = $countryCode ?? '';
            $this->postalCode = $postalCode;
            $this->geo = $geo;
            $this->siteTemplate = $siteTemplate ?? 'v2025';
            $this->palette = $palette ?? 'blue';
            $this->industry = $industry ?? 'services';
            $this->defaultLocale = $defaultLocale ?? 'en-US';
            $this->schemaVersion = $schemaVersion ?? 1;
            $this->siteVersion = $siteVersion ?? 1;
            // siteJson: Accept array (for backward compatibility) or string (for Doctrine)
            // Use standard PHP json_encode() instead of wp_json_encode() for testability
            if ($siteJson !== null) {
                // Parameter is typed as ?array, so we always encode it
                $this->siteJson = json_encode($siteJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $this->siteJson = '{}';
            }
            $this->searchTerms = $searchTerms;
            $this->status = $status ?? 'published';
            $this->publishStatus = $publishStatus ?? 'draft';
            $this->createdAt = $createdAt ?? new \DateTimeImmutable();
            $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
            $this->publishedAt = $publishedAt;
            $this->createdBy = $createdBy;
            $this->updatedBy = $updatedBy;
            $this->currentVersionId = $currentVersionId;
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
}
