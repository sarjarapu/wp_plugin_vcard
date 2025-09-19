<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Version;

final class VersionRepository implements VersionRepositoryInterface
{
    public function __construct(private \wpdb $db) {}

    private function table(): string 
    { 
        return $this->db->prefix . 'minisite_versions'; 
    }

    public function save(Version $version): Version
    {
        $data = [
            'minisite_id' => $version->minisiteId,
            'version_number' => $version->versionNumber,
            'status' => $version->status,
            'label' => $version->label,
            'comment' => $version->comment,
            'created_by' => $version->createdBy,
            'published_at' => $version->publishedAt?->format('Y-m-d H:i:s'),
            'source_version_id' => $version->sourceVersionId,
            
            // Profile fields
            'business_slug' => $version->slugs?->business,
            'location_slug' => $version->slugs?->location,
            'title' => $version->title,
            'name' => $version->name,
            'city' => $version->city,
            'region' => $version->region,
            'country_code' => $version->countryCode,
            'postal_code' => $version->postalCode,
            'location_point' => null, // Will be set separately if needed
            'site_template' => $version->siteTemplate,
            'palette' => $version->palette,
            'industry' => $version->industry,
            'default_locale' => $version->defaultLocale,
            'schema_version' => $version->schemaVersion,
            'site_version' => $version->siteVersion,
            'site_json' => wp_json_encode($version->siteJson),
            'search_terms' => $version->searchTerms,
        ];

        $formats = [
            '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'
        ];

        if ($version->id === null) {
            // Insert new version
            $this->db->insert($this->table(), $data, $formats);
            $version->id = (int) $this->db->insert_id;
            
            // Set location_point if lat/lng exist
            if ($version->geo && $version->geo->lat && $version->geo->lng) {
                $this->db->query($this->db->prepare(
                    "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                    $version->geo->lng, $version->geo->lat, $version->id
                ));
            }
        } else {
            // Update existing version
            $this->db->update(
                $this->table(), 
                $data, 
                ['id' => $version->id], 
                $formats, 
                ['%d']
            );
            
            // Update location_point if lat/lng exist
            if ($version->geo && $version->geo->lat && $version->geo->lng) {
                $this->db->query($this->db->prepare(
                    "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                    $version->geo->lng, $version->geo->lat, $version->id
                ));
            } else {
                // Clear location_point if no geo data
                $this->db->query($this->db->prepare(
                    "UPDATE {$this->table()} SET location_point = NULL WHERE id = %d",
                    $version->id
                ));
            }
        }

        return $version;
    }

    public function findById(int $id): ?Version
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);
        
        if (!$row) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function findByMinisiteId(int $minisiteId, int $limit = 50, int $offset = 0): array
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d 
             ORDER BY version_number DESC 
             LIMIT %d OFFSET %d",
            $minisiteId, $limit, $offset
        );
        
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn($row) => $this->mapRow($row), $rows);
    }

    public function findLatestVersion(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function findLatestDraft(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d AND status = 'draft' 
             ORDER BY version_number DESC 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function findPublishedVersion(int $minisiteId): ?Version
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE minisite_id = %d AND status = 'published' 
             LIMIT 1",
            $minisiteId
        );
        
        $row = $this->db->get_row($sql, ARRAY_A);
        return $row ? $this->mapRow($row) : null;
    }

    public function getNextVersionNumber(int $minisiteId): int
    {
        $sql = $this->db->prepare(
            "SELECT MAX(version_number) as max_version FROM {$this->table()} WHERE minisite_id = %d",
            $minisiteId
        );
        
        $result = $this->db->get_var($sql);
        return $result ? (int) $result + 1 : 1;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table(), ['id' => $id], ['%d']);
        return $result !== false;
    }

    private function mapRow(array $row): Version
    {
        // Create SlugPair if both slugs exist
        $slugs = null;
        if (!empty($row['business_slug']) && !empty($row['location_slug'])) {
            $slugs = new \Minisite\Domain\ValueObjects\SlugPair(
                business: $row['business_slug'],
                location: $row['location_slug']
            );
        }

        // Create GeoPoint from location_point geometry
        $geo = null;
        if (!empty($row['location_point'])) {
            // Extract lat/lng from POINT geometry
            $pointResult = $this->db->get_row($this->db->prepare(
                "SELECT ST_Y(location_point) as lat, ST_X(location_point) as lng FROM {$this->table()} WHERE id = %d",
                $row['id']
            ), ARRAY_A);
            
            if ($pointResult && $pointResult['lat'] && $pointResult['lng']) {
                $geo = new \Minisite\Domain\ValueObjects\GeoPoint(
                    lat: (float) $pointResult['lat'],
                    lng: (float) $pointResult['lng']
                );
            }
        }

        return new Version(
            id: (int) $row['id'],
            minisiteId: (int) $row['minisite_id'],
            versionNumber: (int) $row['version_number'],
            status: $row['status'],
            label: $row['label'],
            comment: $row['comment'],
            createdBy: (int) $row['created_by'],
            createdAt: $row['created_at'] ? new \DateTimeImmutable($row['created_at']) : null,
            publishedAt: $row['published_at'] ? new \DateTimeImmutable($row['published_at']) : null,
            sourceVersionId: $row['source_version_id'] ? (int) $row['source_version_id'] : null,
            
            // Profile fields
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
            siteJson: json_decode($row['site_json'], true) ?: [],
            searchTerms: $row['search_terms'] ?? null
        );
    }
}
