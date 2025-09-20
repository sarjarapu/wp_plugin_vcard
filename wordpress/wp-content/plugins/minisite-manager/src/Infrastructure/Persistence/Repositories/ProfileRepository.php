<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Profile;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

final class ProfileRepository implements ProfileRepositoryInterface
{
    public function __construct(private \wpdb $db) {}

    private function table(): string { return $this->db->prefix . 'minisites'; }

    public function findBySlugs(SlugPair $slugs): ?Profile
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE business_slug=%s AND location_slug=%s LIMIT 1",
            $slugs->business, $slugs->location
        );
        $row = $this->db->get_row($sql, ARRAY_A);
        if (!$row) return null;

        return $this->mapRow($row);
    }

    /**
     * Find profile by individual slug parameters (for race condition checking)
     */
    public function findBySlugParams(string $businessSlug, string $locationSlug): ?Profile
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE business_slug=%s AND location_slug=%s LIMIT 1 FOR UPDATE",
            $businessSlug, $locationSlug
        );
        $row = $this->db->get_row($sql, ARRAY_A);
        if (!$row) return null;

        return $this->mapRow($row);
    }

    /**
     * List profiles owned by a user (v1 minimal: uses created_by as owner surrogate)
     * TODO: Switch to explicit owner_user_id column when added.
     *
     * @return Profile[]
     */
    public function listByOwner(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE created_by=%d ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            $userId, $limit, $offset
        );
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn($r) => $this->mapRow($r), $rows);
    }

    /**
     * Find profile by ID
     */
    public function findById(string $id): ?Profile
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id=%s LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);
        if (!$row) return null;
        return $this->mapRow($row);
    }

    /**
     * Update profile's siteJson data (for editing)
     * TODO: Implement proper versioning with versions table
     */
    public function updateSiteJson(string $id, array $siteJson, int $updatedBy): Profile
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET site_json=%s, updated_by=%d, updated_at=NOW() WHERE id=%s",
            wp_json_encode($siteJson), $updatedBy, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Profile not found or update failed.');
        }
        
        return $this->findById($id);
    }

    /**
     * Update profile's siteJson data and coordinates (for editing)
     * TODO: Implement proper versioning with versions table
     */
    public function updateSiteJsonWithCoordinates(string $id, array $siteJson, ?float $lat, ?float $lng, int $updatedBy): Profile
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET site_json=%s, updated_by=%d, updated_at=NOW() WHERE id=%s",
            wp_json_encode($siteJson), $updatedBy, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Profile not found or update failed.');
        }
        
        // Update POINT column if coordinates are set
        if ($lat !== null && $lng !== null) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                $lng, $lat, $id
            ));
        } else {
            // Clear location_point if no coordinates
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = NULL WHERE id = %s",
                $id
            ));
        }
        
        return $this->findById($id);
    }

    /**
     * Update the current version ID for a profile
     */
    public function updateCurrentVersionId(string $id, int $versionId): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET _minisite_current_version_id = %d WHERE id = %s",
            $versionId, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Profile not found or update failed.');
        }
    }

    /**
     * Update only coordinates for a profile (used when saving drafts)
     */
    public function updateCoordinates(string $id, ?float $lat, ?float $lng, int $updatedBy): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET updated_by = %d, updated_at = NOW() WHERE id = %s",
            $updatedBy, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Profile not found or update failed.');
        }
        
        // Update POINT column if coordinates are set
        if ($lat !== null && $lng !== null) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                $lng, $lat, $id
            ));
        } else {
            // Clear location_point if no coordinates
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = NULL WHERE id = %s",
                $id
            ));
        }
    }

    public function save(Profile $p, int $expectedSiteVersion): Profile
    {
        // Build normalized search_terms (simple example; replace with your builder later)
        $search = trim(strtolower("{$p->name} {$p->city} {$p->industry} {$p->palette} {$p->title}"));

        // Update existing by slugs + expected version (optimistic lock)
        $data = [
            'title'          => $p->title,
            'name'           => $p->name,
            'city'           => $p->city,
            'region'         => $p->region,
            'country_code'   => $p->countryCode,
            'postal_code'    => $p->postalCode,
            'site_template'  => $p->siteTemplate,
            'palette'        => $p->palette,
            'industry'       => $p->industry,
            'default_locale' => $p->defaultLocale,
            'schema_version' => $p->schemaVersion,
            'site_json'      => wp_json_encode($p->siteJson),
            'search_terms'   => $search,
            'status'         => $p->status,
            'updated_by'     => $p->updatedBy,
        ];
        $format = ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%d'];

        // If row exists, update w/ optimistic lock
        $sql = $this->db->prepare(
            "UPDATE {$this->table()}
             SET title=%s,name=%s,city=%s,region=%s,country_code=%s,postal_code=%s,
                 site_template=%s,palette=%s,industry=%s,default_locale=%s,
                 schema_version=%d,site_json=%s,search_terms=%s,status=%s,updated_by=%d,
                 site_version = site_version + 1
             WHERE business_slug=%s AND location_slug=%s AND site_version=%d",
            $data['title'],$data['name'],$data['city'],$data['region'],$data['country_code'],$data['postal_code'],
            $data['site_template'],$data['palette'],$data['industry'],$data['default_locale'],
            $data['schema_version'],$data['site_json'],$data['search_terms'],$data['status'],$data['updated_by'],
            $p->slugs->business,$p->slugs->location,$expectedSiteVersion
        );
        $this->db->query($sql);

        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Concurrent modification detected (optimistic lock failed).');
        }

        // Sync POINT column if lat/lng set
        if ($p->geo->isSet()) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f,%f),4326)
                 WHERE business_slug=%s AND location_slug=%s",
                $p->geo->lng, $p->geo->lat, $p->slugs->business, $p->slugs->location
            ));
        }

        // Re-fetch updated row to return fresh entity (with new site_version)
        $fresh = $this->findBySlugs($p->slugs);
        if (!$fresh) throw new \RuntimeException('Failed to reload profile after save.');
        return $fresh;
    }

    private function mapRow(array $r): Profile
    {
        // Extract lat/lng from location_point geometry
        $geo = null;
        if (!empty($r['location_point'])) {
            // The migration data was inserted as POINT(lng, lat), so ST_Y() returns lng and ST_X() returns lat
            $pointResult = $this->db->get_row($this->db->prepare(
                "SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat FROM {$this->table()} WHERE id = %s",
                $r['id']
            ), ARRAY_A);
            
            if ($pointResult && $pointResult['lat'] && $pointResult['lng']) {
                $geo = new GeoPoint(
                    lat: (float) $pointResult['lat'],
                    lng: (float) $pointResult['lng']
                );
            }
        }

        return new Profile(
            id:            (string)$r['id'],
            slugs:         new SlugPair($r['business_slug'], $r['location_slug']),
            title:         $r['title'],
            name:          $r['name'],
            city:          $r['city'],
            region:        $r['region'] ?: null,
            countryCode:   $r['country_code'],
            postalCode:    $r['postal_code'] ?: null,
            geo:           $geo,
            siteTemplate:  $r['site_template'],
            palette:       $r['palette'],
            industry:      $r['industry'],
            defaultLocale: $r['default_locale'],
            schemaVersion: (int)$r['schema_version'],
            siteVersion:   (int)$r['site_version'],
            siteJson:      json_decode((string)$r['site_json'], true) ?: [],
            searchTerms:   $r['search_terms'] ?: null,
            status:        $r['status'],
            createdAt:     $r['created_at'] ? new \DateTimeImmutable($r['created_at']) : null,
            updatedAt:     $r['updated_at'] ? new \DateTimeImmutable($r['updated_at']) : null,
            publishedAt:   $r['published_at'] ? new \DateTimeImmutable($r['published_at']) : null,
            createdBy:     $r['created_by'] ? (int)$r['created_by'] : null,
            updatedBy:     $r['updated_by'] ? (int)$r['updated_by'] : null,
            currentVersionId: $r['_minisite_current_version_id'] ? (int)$r['_minisite_current_version_id'] : null,
            isBookmarked:  false,  // Will be set by TimberRenderer
            canEdit:       false   // Will be set by TimberRenderer
        );
    }
}