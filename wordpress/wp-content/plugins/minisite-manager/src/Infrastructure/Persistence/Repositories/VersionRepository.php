<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Version;

class VersionRepository implements VersionRepositoryInterface
{
    public function __construct(private \wpdb $db)
    {
    }

    private function table(): string
    {
        return $this->db->prefix . 'minisite_versions';
    }

    public function save(Version $version): Version
    {
        $data = array(
            'minisite_id'       => $version->minisiteId,
            'version_number'    => $version->versionNumber,
            'status'            => $version->status,
            'label'             => $version->label,
            'comment'           => $version->comment,
            'created_by'        => $version->createdBy,
            'created_at'        => $version->createdAt?->format('Y-m-d H:i:s') ?? current_time('mysql'),
            'published_at'      => $version->publishedAt?->format('Y-m-d H:i:s'),
            'source_version_id' => $version->sourceVersionId,

            // Minisite fields
            'business_slug'     => $version->slugs?->business,
            'location_slug'     => $version->slugs?->location,
            'title'             => $version->title,
            'name'              => $version->name,
            'city'              => $version->city,
            'region'            => $version->region,
            'country_code'      => $version->countryCode,
            'postal_code'       => $version->postalCode,
            'site_template'     => $version->siteTemplate,
            'palette'           => $version->palette,
            'industry'          => $version->industry,
            'default_locale'    => $version->defaultLocale,
            'schema_version'    => $version->schemaVersion,
            'site_version'      => $version->siteVersion,
            'site_json'         => wp_json_encode($version->siteJson),
            'search_terms'      => $version->searchTerms,
        );

        $formats = array(
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
        );

        if ($version->id === null) {
            // Insert new version
            $this->db->insert($this->table(), $data, $formats);
            $version->id = (int) $this->db->insert_id;

            // Set location_point if lat/lng exist
            if ($version->geo && $version->geo->isSet()) {
                $result = $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->table()} SET location_point = POINT(%f, %f) WHERE id = %d",
                        $version->geo->getLng(),
                        $version->geo->getLat(),
                        $version->id
                    )
                );
                // Debug: Check if the update was successful
                if ($result === false) {
                    $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('version-repository');
                    $logger->error('Failed to update location_point for version', [
                        'version_id' => $version->id,
                    ]);
                }
            }
        } else {
            // Update existing version
            $this->db->update(
                $this->table(),
                $data,
                array( 'id' => $version->id ),
                $formats,
                array( '%d' )
            );

            // Update location_point if lat/lng exist
            if ($version->geo && $version->geo->isSet()) {
                $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->table()} SET location_point = POINT(%f, %f) WHERE id = %d",
                        $version->geo->getLng(),
                        $version->geo->getLat(),
                        $version->id
                    )
                );
            } else {
                // Clear location_point if no geo data
                $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->table()} SET location_point = NULL WHERE id = %d",
                        $version->id
                    )
                );
            }
        }

        // Return the saved version with all data properly loaded from database
        return $this->findById($version->id);
    }

    public function findById(int $id): ?Version
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);

        if (! $row) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function findByMinisiteId(string $minisiteId, int $limit = 50, int $offset = 0): array
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %s 
             ORDER BY version_number DESC 
             LIMIT %d OFFSET %d",
            $minisiteId,
            $limit,
            $offset
        );

        $rows = $this->db->get_results($sql, ARRAY_A) ?: array();
        return array_map(fn($row) => $this->mapRow($row), $rows);
    }

    public function findLatestVersion(string $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %s 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );

        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function findLatestDraft(string $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %s AND status = 'draft' 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );

        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    /**
     * Get the latest draft for editing, creating one from published version if needed
     * This ensures there's always a draft available for editing
     */
    public function getLatestDraftForEditing(string $minisiteId): Version
    {
        // 1. Find the latest version (could be draft or published)
        $latestVersion = $this->findLatestVersion($minisiteId);
        if (! $latestVersion) {
            throw new \RuntimeException('No version found for minisite.');
        }

        // 2. If latest version is published, create a draft copy from it
        if ($latestVersion->status === 'published') {
            return $this->createDraftFromVersion($latestVersion);
        }

        // 3. If latest version is already a draft, return it
        return $latestVersion;
    }

    /**
     * Create a new draft version from an existing version
     */
    public function createDraftFromVersion(Version $sourceVersion): Version
    {
        $nextVersion = $this->getNextVersionNumber($sourceVersion->minisiteId);

        $draftVersion = new Version(
            id: null,
            minisiteId: $sourceVersion->minisiteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: "Draft from v{$sourceVersion->versionNumber}",
            comment: "Created from version {$sourceVersion->versionNumber} for editing",
            createdBy: $sourceVersion->createdBy,
            createdAt: new \DateTimeImmutable(),
            publishedAt: null,
            sourceVersionId: $sourceVersion->id,
            siteJson: $sourceVersion->siteJson,
            // Copy all minisite fields
            slugs: $sourceVersion->slugs,
            title: $sourceVersion->title,
            name: $sourceVersion->name,
            city: $sourceVersion->city,
            region: $sourceVersion->region,
            countryCode: $sourceVersion->countryCode,
            postalCode: $sourceVersion->postalCode,
            geo: $sourceVersion->geo,
            siteTemplate: $sourceVersion->siteTemplate,
            palette: $sourceVersion->palette,
            industry: $sourceVersion->industry,
            defaultLocale: $sourceVersion->defaultLocale,
            schemaVersion: $sourceVersion->schemaVersion,
            siteVersion: $sourceVersion->siteVersion,
            searchTerms: $sourceVersion->searchTerms
        );

        return $this->save($draftVersion);
    }

    public function findPublishedVersion(string $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %s AND status = 'published' 
             LIMIT 1",
            $minisiteId
        );

        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function getNextVersionNumber(string $minisiteId): int
    {
        $sql = $this->db->prepare(
            "SELECT MAX(version_number) as max_version FROM {$this->table()} WHERE minisite_id = %s",
            $minisiteId
        );

        $result = $this->db->get_var($sql);
        return $result ? (int) $result + 1 : 1;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table(), array( 'id' => $id ), array( '%d' ));
        return $result !== false;
    }

    private function mapRow(array $row): Version
    {
        // Create SlugPair if both slugs exist
        $slugs = null;
        if (! empty($row['business_slug']) && ! empty($row['location_slug'])) {
            $slugs = new \Minisite\Domain\ValueObjects\SlugPair(
                business: $row['business_slug'],
                location: $row['location_slug']
            );
        }

        // Create GeoPoint from location_point geometry
        $geo = null;
        // Check if we have location_point data and try to extract it
        if (! empty($row['location_point']) && $row['id']) {
            try {
                // Extract lat/lng from POINT geometry
                // POINT is stored as POINT(lng, lat), so ST_X() returns lng and ST_Y() returns lat
                $pointResult = $this->db->get_row(
                    $this->db->prepare(
                        "SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat " .
                        "FROM {$this->table()} WHERE id = %d",
                        $row['id']
                    ),
                    ARRAY_A
                );

                if ($pointResult && $pointResult['lat'] !== null && $pointResult['lng'] !== null) {
                    $geo = new \Minisite\Domain\ValueObjects\GeoPoint(
                        lat: (float) $pointResult['lat'],
                        lng: (float) $pointResult['lng']
                    );
                }
            } catch (\Exception $e) {
                // If spatial functions fail, geo remains null
                // This is expected if MySQL spatial functions are not available (e.g., in unit tests)
            }
        }

        $decodedSiteJson = json_decode($row['site_json'], true) ?: array();

        return new Version(
            id: (int) $row['id'],
            minisiteId: $row['minisite_id'],
            versionNumber: (int) $row['version_number'],
            status: $row['status'],
            label: $row['label'],
            comment: $row['comment'],
            createdBy: (int) $row['created_by'],
            createdAt: $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null,
            publishedAt: $row['published_at'] ? new \DateTimeImmutable($row['published_at']) : null,
            sourceVersionId: $row['source_version_id'] ? (int) $row['source_version_id'] : null,
            siteJson: $decodedSiteJson,
            // Minisite fields
            slugs: $slugs,
            title: $row['title'] ?? null,
            name: $row['name'] ?? null,
            city: $row['city'] ?? null,
            region: $row['region'] ?? null,
            countryCode: $row['country_code'] ?? null,
            postalCode: $row['postal_code'] ?? null,
            geo: $geo,
            siteTemplate: $row['site_template'] ?? null,
            palette: $row['palette'] ?? null,
            industry: $row['industry'] ?? null,
            defaultLocale: $row['default_locale'] ?? null,
            schemaVersion: $row['schema_version'] ? (int) $row['schema_version'] : null,
            siteVersion: $row['site_version'] ? (int) $row['site_version'] : null,
            searchTerms: $row['search_terms'] ?? null
        );
    }
}
