<?php

declare(strict_types=1);

namespace Minisite\Features\VersionManagement\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service for seeding sample version data using Doctrine
 *
 * This follows the same pattern as ReviewSeederService.
 * All version operations (creation, editing, seeding) should use VersionRepository
 * through this service or directly.
 */
class VersionSeederService
{
    private LoggerInterface $logger;

    public function __construct(
        private VersionRepositoryInterface $versionRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('version-seeder');
    }

    /**
     * Create a Version entity from JSON data array
     *
     * Populates all fields from JSON data, with sensible defaults for missing fields.
     *
     * @param string $minisiteId The minisite ID
     * @param array $versionData Version data from JSON
     * @return Version The created version entity
     */
    public function createVersionFromJsonData(string $minisiteId, array $versionData): Version
    {
        $nowUser = get_current_user_id() ?: null;

        // Create Version entity
        $version = new Version();

        // Core required fields
        $version->minisiteId = $minisiteId;
        $version->versionNumber = isset($versionData['versionNumber'])
            ? (int) $versionData['versionNumber']
            : 1;
        $version->status = $versionData['status'] ?? 'draft';
        $version->createdBy = isset($versionData['createdBy'])
            && $versionData['createdBy'] !== null
            ? (int) $versionData['createdBy']
            : ($nowUser ?? 0);

        // Optional metadata fields
        $version->label = $versionData['label'] ?? null;
        $version->comment = $versionData['comment'] ?? null;
        $version->sourceVersionId = isset($versionData['sourceVersionId'])
            && $versionData['sourceVersionId'] !== null
            ? (int) $versionData['sourceVersionId']
            : null;

        // Timestamps - parse from JSON or use current time
        if (isset($versionData['createdAt']) && $versionData['createdAt']) {
            $version->createdAt = new \DateTimeImmutable($versionData['createdAt']);
        } else {
            $version->createdAt = new \DateTimeImmutable();
        }

        if (isset($versionData['publishedAt']) && $versionData['publishedAt']) {
            $version->publishedAt = new \DateTimeImmutable($versionData['publishedAt']);
        } elseif ($version->status === 'published') {
            // If status is published but no publishedAt, use createdAt
            $version->publishedAt = $version->createdAt;
        } else {
            $version->publishedAt = null;
        }

        // Slug fields
        $version->businessSlug = $versionData['businessSlug'] ?? null;
        $version->locationSlug = $versionData['locationSlug'] ?? null;
        if ($version->businessSlug !== null && $version->locationSlug !== null) {
            $version->slugs = new SlugPair(
                business: $version->businessSlug,
                location: $version->locationSlug
            );
        }

        // Business information fields
        $version->title = $versionData['title'] ?? null;
        $version->name = $versionData['name'] ?? null;
        $version->city = $versionData['city'] ?? null;
        $version->region = $versionData['region'] ?? null;
        $version->countryCode = $versionData['countryCode'] ?? null;
        $version->postalCode = $versionData['postalCode'] ?? null;

        // Location point (GeoPoint) - handle latitude/longitude
        if (isset($versionData['location']) && is_array($versionData['location'])) {
            $lat = isset($versionData['location']['latitude'])
                ? (float) $versionData['location']['latitude']
                : null;
            $lng = isset($versionData['location']['longitude'])
                ? (float) $versionData['location']['longitude']
                : null;
            if ($lat !== null && $lng !== null) {
                $version->geo = new GeoPoint(lat: $lat, lng: $lng);
            }
        } elseif (isset($versionData['latitude']) && isset($versionData['longitude'])) {
            // Alternative format: direct latitude/longitude fields
            $version->geo = new GeoPoint(
                lat: (float) $versionData['latitude'],
                lng: (float) $versionData['longitude']
            );
        }

        // Site configuration fields
        $version->siteTemplate = $versionData['siteTemplate'] ?? null;
        $version->palette = $versionData['palette'] ?? null;
        $version->industry = $versionData['industry'] ?? null;
        $version->defaultLocale = $versionData['defaultLocale'] ?? null;
        $version->schemaVersion = isset($versionData['schemaVersion'])
            && $versionData['schemaVersion'] !== null
            ? (int) $versionData['schemaVersion']
            : null;
        $version->siteVersion = isset($versionData['siteVersion'])
            && $versionData['siteVersion'] !== null
            ? (int) $versionData['siteVersion']
            : null;
        $version->searchTerms = $versionData['searchTerms'] ?? null;

        // siteJson - can be array or string
        if (isset($versionData['siteJson'])) {
            if (is_array($versionData['siteJson'])) {
                $version->setSiteJsonFromArray($versionData['siteJson']);
            } else {
                $version->siteJson = (string) $versionData['siteJson'];
            }
        } else {
            $version->siteJson = '{}';
        }

        return $version;
    }

    /**
     * Create an initial version from a minisite entity
     *
     * This creates version 1 as published with all fields copied from the minisite.
     * Used for seeding test data where we want version 1 to match the minisite.
     *
     * @param \Minisite\Features\MinisiteManagement\Domain\Entities\Minisite $minisite The minisite entity
     * @param string $label Optional label for the version (default: 'Initial version')
     * @param string $comment Optional comment for the version (default: 'Migrated from existing data')
     * @return Version The created version entity
     */
    public function createInitialVersionFromMinisite(
        \Minisite\Features\MinisiteManagement\Domain\Entities\Minisite $minisite,
        string $label = 'Initial version',
        string $comment = 'Migrated from existing data'
    ): Version {
        $nowUser = get_current_user_id() ?: null;

        // Create Version entity
        $version = new Version();

        // Core required fields
        $version->minisiteId = $minisite->id;
        $version->versionNumber = 1;
        $version->status = 'published';
        $version->label = $label;
        $version->comment = $comment;
        $version->createdBy = $nowUser ?? $minisite->createdBy ?? 0;
        $version->sourceVersionId = null;

        // Timestamps
        $version->createdAt = $minisite->createdAt ?? new \DateTimeImmutable();
        $version->publishedAt = $minisite->publishedAt ?? $version->createdAt;

        // Copy all profile fields from minisite
        $version->businessSlug = $minisite->businessSlug;
        $version->locationSlug = $minisite->locationSlug;
        if ($minisite->slugs !== null) {
            $version->slugs = new SlugPair(
                business: $minisite->slugs->business,
                location: $minisite->slugs->location
            );
        }

        $version->title = $minisite->title;
        $version->name = $minisite->name;
        $version->city = $minisite->city;
        $version->region = $minisite->region;
        $version->countryCode = $minisite->countryCode;
        $version->postalCode = $minisite->postalCode;

        // Copy geo point
        if ($minisite->geo !== null) {
            $version->geo = new GeoPoint(
                lat: $minisite->geo->getLat(),
                lng: $minisite->geo->getLng()
            );
        }

        // Copy site configuration
        $version->siteTemplate = $minisite->siteTemplate;
        $version->palette = $minisite->palette;
        $version->industry = $minisite->industry;
        $version->defaultLocale = $minisite->defaultLocale;
        $version->schemaVersion = $minisite->schemaVersion;
        $version->siteVersion = $minisite->siteVersion;
        $version->searchTerms = $minisite->searchTerms;

        // Copy siteJson
        $version->siteJson = $minisite->siteJson;

        return $version;
    }

    /**
     * Seed sample versions for a minisite
     *
     * This is the Doctrine-based replacement for the old version seeding.
     * Now loads all fields from JSON data.
     *
     * @param string $minisiteId The minisite ID to seed versions for
     * @param array $versions Array of version data from JSON (all fields supported)
     */
    public function seedVersionsForMinisite(string $minisiteId, array $versions): void
    {
        foreach ($versions as $versionData) {
            $version = $this->createVersionFromJsonData($minisiteId, $versionData);
            $this->versionRepository->save($version);
        }
    }

    /**
     * Load versions from JSON file
     *
     * @param string $jsonFile JSON filename (e.g., 'acme-dental-versions.json')
     * @return array Array of version data
     * @throws \RuntimeException If file not found or invalid JSON
     */
    protected function loadVersionsFromJson(string $jsonFile): array
    {
        $jsonPath = MINISITE_PLUGIN_DIR . 'data/json/versions/' . $jsonFile;

        if (! file_exists($jsonPath)) {
            throw new \RuntimeException('JSON file not found: ' . esc_html($jsonPath));
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid JSON in file: ' . esc_html($jsonFile) . '. Error: ' . esc_html(json_last_error_msg())
            );
        }

        if (! isset($data['versions']) || ! is_array($data['versions'])) {
            throw new \RuntimeException(
                'Invalid JSON structure in file: ' . esc_html($jsonFile) . '. Missing \'versions\' array.'
            );
        }

        return $data['versions'];
    }

    /**
     * Seed all sample versions for the standard minisites
     *
     * This seeds versions for:
     * - ACME Dental (Dallas)
     * - Lotus Textiles (Mumbai)
     * - Green Bites (London)
     * - Swift Transit (Sydney)
     *
     * Versions are loaded from JSON files in data/json/versions/
     *
     * Note: This is sample data (not test data). The "Test" keyword is reserved for testing phases.
     *
     * @param array $minisiteIds Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT'
     */
    public function seedAllSampleVersions(array $minisiteIds): void
    {
        // Map of minisite keys to their JSON version files
        $versionFiles = array(
            'ACME' => 'acme-dental-versions.json',
            'LOTUS' => 'lotus-textiles-versions.json',
            'GREEN' => 'green-bites-versions.json',
            'SWIFT' => 'swift-transit-versions.json',
        );

        foreach ($versionFiles as $key => $jsonFile) {
            if (! empty($minisiteIds[$key])) {
                try {
                    $versions = $this->loadVersionsFromJson($jsonFile);
                    $this->seedVersionsForMinisite($minisiteIds[$key], $versions);
                } catch (\RuntimeException $e) {
                    // Log error but continue with other minisites
                    $this->logger->warning('Failed to load versions from JSON file', array(
                        'json_file' => $jsonFile,
                        'minisite_key' => $key,
                        'minisite_id' => $minisiteIds[$key],
                        'error' => $e->getMessage(),
                    ));
                }
            }
        }
    }
}
