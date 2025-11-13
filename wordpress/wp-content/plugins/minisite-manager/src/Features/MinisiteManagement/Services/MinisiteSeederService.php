<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteManagement\Services;

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service for seeding sample minisite data using Doctrine
 *
 * This replaces the old wpdb-based minisite insertion in _1_0_0_CreateBase.
 * All minisite operations (creation, editing, seeding) should use MinisiteRepository
 * through this service or directly.
 */
class MinisiteSeederService
{
    private LoggerInterface $logger;

    public function __construct(
        private MinisiteRepositoryInterface $minisiteRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-seeder');
    }

    /**
     * Load minisite data from JSON file
     *
     * @param string $jsonFile JSON filename (e.g., 'acme-dental.json')
     * @return array Minisite data array
     * @throws \RuntimeException If file not found or invalid JSON
     */
    protected function loadMinisiteFromJson(string $jsonFile): array
    {
        $jsonPath = MINISITE_PLUGIN_DIR . 'data/json/minisites/' . $jsonFile;

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

        if (! isset($data['minisite'])) {
            throw new \RuntimeException(
                'Invalid JSON structure in file: ' . esc_html($jsonFile) . '. Missing \'minisite\' property.'
            );
        }

        return $data['minisite'];
    }

    /**
     * Convert location format between JSON and database formats
     *
     * JSON format: {latitude, longitude}
     * Database format: GeoPoint(lat, lng)
     *
     * @param array $data Minisite data array
     * @return array Minisite data with location converted
     */
    protected function convertLocationFormat(array $data): array
    {
        if (isset($data['location']) && is_array($data['location'])) {
            // Convert from JSON format {latitude, longitude} to GeoPoint format
            if (isset($data['location']['latitude']) && isset($data['location']['longitude'])) {
                $data['location_point'] = array(
                    'latitude' => (float) $data['location']['latitude'],
                    'longitude' => (float) $data['location']['longitude'],
                );
                unset($data['location']); // Remove the JSON location format
            }
        }

        return $data;
    }

    /**
     * Set computed and audit fields that are not in JSON
     *
     * @param array $data Minisite data array
     * @return array Minisite data with computed fields set
     */
    protected function setComputedFields(array $data): array
    {
        $now = new \DateTimeImmutable();
        $userId = get_current_user_id() ?: null;

        // Set audit fields - always use current values for seeding
        if (! isset($data['created_at']) || empty($data['created_at'])) {
            $data['created_at'] = $now->format('Y-m-d H:i:s');
        }
        if (! isset($data['updated_at']) || empty($data['updated_at'])) {
            $data['updated_at'] = $now->format('Y-m-d H:i:s');
        }
        if (! isset($data['published_at']) || empty($data['published_at'])) {
            $data['published_at'] = $now->format('Y-m-d H:i:s');
        }
        $data['created_by'] = $userId; // Always reset to current user for proper ownership
        $data['updated_by'] = $userId; // Always reset to current user for proper ownership

        // Set computed slug if not provided
        if (! isset($data['slug']) && isset($data['business_slug']) && isset($data['location_slug'])) {
            $data['slug'] = $data['business_slug'] . '-' . $data['location_slug'];
        }

        // Set version reference (will be updated after version creation)
        $data['_minisite_current_version_id'] = null;

        return $data;
    }

    /**
     * Create a Minisite entity from JSON data array
     *
     * Populates all fields from JSON data, with sensible defaults for missing fields.
     *
     * @param array $minisiteData Minisite data from JSON (with 'minisite' key extracted)
     * @return Minisite The created minisite entity
     */
    public function createMinisiteFromJsonData(array $minisiteData): Minisite
    {
        // Convert location format
        $minisiteData = $this->convertLocationFormat($minisiteData);

        // Set computed fields
        $minisiteData = $this->setComputedFields($minisiteData);

        // Create Minisite entity
        $minisite = new Minisite();

        // Core required fields
        $minisite->id = $minisiteData['id'] ?? \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $minisite->slug = $minisiteData['slug'] ?? null;
        $minisite->businessSlug = $minisiteData['business_slug'] ?? null;
        $minisite->locationSlug = $minisiteData['location_slug'] ?? null;
        $minisite->title = $minisiteData['title'] ?? '';
        $minisite->name = $minisiteData['name'] ?? '';
        $minisite->city = $minisiteData['city'] ?? '';
        $minisite->region = $minisiteData['region'] ?? null;
        $minisite->countryCode = $minisiteData['country_code'] ?? 'US';
        $minisite->postalCode = $minisiteData['postal_code'] ?? null;

        // Location point (GeoPoint) - handle latitude/longitude
        if (isset($minisiteData['location_point']) && is_array($minisiteData['location_point'])) {
            $lat = isset($minisiteData['location_point']['latitude'])
                ? (float) $minisiteData['location_point']['latitude']
                : null;
            $lng = isset($minisiteData['location_point']['longitude'])
                ? (float) $minisiteData['location_point']['longitude']
                : null;
            if ($lat !== null && $lng !== null) {
                $minisite->geo = new GeoPoint(lat: $lat, lng: $lng);
            }
        }

        // SlugPair value object
        if ($minisite->businessSlug !== null && $minisite->locationSlug !== null) {
            $minisite->slugs = new SlugPair(
                business: $minisite->businessSlug,
                location: $minisite->locationSlug
            );
        }

        // Site configuration fields
        $minisite->siteTemplate = $minisiteData['site_template'] ?? 'v2025';
        $minisite->palette = $minisiteData['palette'] ?? 'blue';
        $minisite->industry = $minisiteData['industry'] ?? 'services';
        $minisite->defaultLocale = $minisiteData['default_locale'] ?? 'en-US';
        $minisite->schemaVersion = isset($minisiteData['schema_version'])
            ? (int) $minisiteData['schema_version']
            : 1;
        $minisite->siteVersion = isset($minisiteData['site_version'])
            ? (int) $minisiteData['site_version']
            : 1;
        $minisite->searchTerms = $minisiteData['search_terms'] ?? null;
        $minisite->status = $minisiteData['status'] ?? 'published';
        $minisite->publishStatus = $minisiteData['publish_status'] ?? 'published';

        // siteJson - can be array or string
        if (isset($minisiteData['site_json'])) {
            if (is_array($minisiteData['site_json'])) {
                $minisite->setSiteJsonFromArray($minisiteData['site_json']);
            } else {
                $minisite->siteJson = (string) $minisiteData['site_json'];
            }
        } else {
            $minisite->siteJson = '{}';
        }

        // Timestamps - parse from JSON or use current time
        if (isset($minisiteData['created_at']) && $minisiteData['created_at']) {
            $minisite->createdAt = new \DateTimeImmutable($minisiteData['created_at']);
        } else {
            $minisite->createdAt = new \DateTimeImmutable();
        }

        if (isset($minisiteData['updated_at']) && $minisiteData['updated_at']) {
            $minisite->updatedAt = new \DateTimeImmutable($minisiteData['updated_at']);
        } else {
            $minisite->updatedAt = new \DateTimeImmutable();
        }

        if (isset($minisiteData['published_at']) && $minisiteData['published_at']) {
            $minisite->publishedAt = new \DateTimeImmutable($minisiteData['published_at']);
        } else {
            $minisite->publishedAt = new \DateTimeImmutable();
        }

        // User IDs
        $minisite->createdBy = isset($minisiteData['created_by'])
            && $minisiteData['created_by'] !== null
            ? (int) $minisiteData['created_by']
            : (get_current_user_id() ?: null);
        $minisite->updatedBy = isset($minisiteData['updated_by'])
            && $minisiteData['updated_by'] !== null
            ? (int) $minisiteData['updated_by']
            : (get_current_user_id() ?: null);

        // Current version ID (will be updated after version creation)
        $minisite->currentVersionId = isset($minisiteData['_minisite_current_version_id'])
            && $minisiteData['_minisite_current_version_id'] !== null
            ? (int) $minisiteData['_minisite_current_version_id']
            : null;

        return $minisite;
    }

    /**
     * Seed all sample minisites for the standard test data
     *
     * This seeds minisites for:
     * - ACME Dental (Dallas)
     * - Lotus Textiles (Mumbai)
     * - Green Bites (London)
     * - Swift Transit (Sydney)
     *
     * Minisites are loaded from JSON files in data/json/minisites/
     *
     * @return array Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT' => minisite IDs
     */
    public function seedAllTestMinisites(): array
    {
        // Map of minisite keys to their JSON files
        $minisiteFiles = array(
            'ACME' => 'acme-dental.json',
            'LOTUS' => 'lotus-textiles.json',
            'GREEN' => 'green-bites.json',
            'SWIFT' => 'swift-transit.json',
        );

        $minisiteIds = array();

        foreach ($minisiteFiles as $key => $jsonFile) {
            try {
                // Load JSON data
                $minisiteData = $this->loadMinisiteFromJson($jsonFile);

                // Generate new ID for each minisite (override JSON ID)
                $minisiteData['id'] = \Minisite\Domain\Services\MinisiteIdGenerator::generate();

                // Create entity from JSON data
                $minisite = $this->createMinisiteFromJsonData($minisiteData);

                // Save using repository
                $savedMinisite = $this->minisiteRepository->insert($minisite);

                $minisiteIds[$key] = $savedMinisite->id;

                $this->logger->info('Seeded minisite', array(
                    'key' => $key,
                    'minisite_id' => $savedMinisite->id,
                    'business_slug' => $savedMinisite->businessSlug,
                    'location_slug' => $savedMinisite->locationSlug,
                ));
            } catch (\RuntimeException $e) {
                // Log error but continue with other minisites
                $this->logger->warning('Failed to seed minisite from JSON file', array(
                    'json_file' => $jsonFile,
                    'minisite_key' => $key,
                    'error' => $e->getMessage(),
                ));
            }
        }

        return $minisiteIds;
    }
}

