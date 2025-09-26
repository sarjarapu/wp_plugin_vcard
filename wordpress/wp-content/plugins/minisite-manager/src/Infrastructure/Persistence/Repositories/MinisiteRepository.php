<?php
namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

final class MinisiteRepository implements MinisiteRepositoryInterface
{
    public function __construct(private \wpdb $db) {}

    private function table(): string { return $this->db->prefix . 'minisites'; }

    public function findBySlugs(SlugPair $slugs): ?Minisite
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
     * Find minisite by individual slug parameters (for race condition checking)
     */
    public function findBySlugParams(string $businessSlug, string $locationSlug): ?Minisite
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
     * List minisites owned by a user (v1 minimal: uses created_by as owner surrogate)
     * TODO: Switch to explicit owner_user_id column when added.
     *
     * @return Minisite[]
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
     * Find minisite by ID
     */
    public function findById(string $id): ?Minisite
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id=%s LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);
        if (!$row) return null;
        return $this->mapRow($row);
    }


    /**
     * Update the current version ID for a minisite
     */
    public function updateCurrentVersionId(string $id, int $versionId): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET _minisite_current_version_id = %d WHERE id = %s",
            $versionId, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Update only coordinates for a minisite (used when saving drafts)
     */
    public function updateCoordinates(string $id, ?float $lat, ?float $lng, int $updatedBy): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET updated_by = %d, updated_at = NOW() WHERE id = %s",
            $updatedBy, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
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

    /**
     * Update the slug for a minisite (for draft creation)
     */
    public function updateSlug(string $id, string $slug): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET slug=%s, updated_at=NOW() WHERE id=%s",
            $slug, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Update business and location slugs for a minisite (for publishing)
     */
    public function updateSlugs(string $id, string $businessSlug, string $locationSlug): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET business_slug=%s, location_slug=%s, updated_at=NOW() WHERE id=%s",
            $businessSlug, $locationSlug, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Update the publish status for a minisite
     */
    public function updatePublishStatus(string $id, string $publishStatus): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET publish_status=%s, updated_at=NOW() WHERE id=%s",
            $publishStatus, $id
        );
        $this->db->query($sql);
        
        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Publish a minisite using the versioning system
     * Uses latest draft version, with fallback to latest version if no draft exists
     */
    public function publishMinisite(string $id): void
    {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $versionRepo = new \Minisite\Infrastructure\Persistence\Repositories\VersionRepository($this->db);
            
            // Try to find latest draft first (preferred)
            $versionToPublish = $versionRepo->findLatestDraft($id);
            
            if (!$versionToPublish) {
                // Fallback: find latest version (could be published)
                $latestVersion = $versionRepo->findLatestVersion($id);
                
                if (!$latestVersion) {
                    throw new \RuntimeException('No version found for minisite.');
                }
                
                // If latest version is already published, create a new draft from it
                if ($latestVersion->status === 'published') {
                    $versionToPublish = $this->createDraftFromVersion($latestVersion, $versionRepo);
                } else {
                    $versionToPublish = $latestVersion;
                }
            }
            
            // Move current published version to draft (if exists)
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'draft' 
                 WHERE minisite_id = %s AND status = 'published'",
                $id
            ));
            
            // Publish the target version
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'published', published_at = NOW() 
                 WHERE id = %d",
                $versionToPublish->id
            ));
            
            // Update main table with published content
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table()} 
                 SET site_json = %s, 
                     title = %s,
                     name = %s,
                     city = %s,
                     region = %s,
                     country_code = %s,
                     postal_code = %s,
                     site_template = %s,
                     palette = %s,
                     industry = %s,
                     default_locale = %s,
                     schema_version = %d,
                     site_version = %d,
                     search_terms = %s,
                     status = 'published',
                     publish_status = 'published',
                     _minisite_current_version_id = %d, 
                     updated_at = NOW() 
                 WHERE id = %s",
                wp_json_encode($versionToPublish->siteJson),
                $versionToPublish->title,
                $versionToPublish->name,
                $versionToPublish->city,
                $versionToPublish->region,
                $versionToPublish->countryCode,
                $versionToPublish->postalCode,
                $versionToPublish->siteTemplate,
                $versionToPublish->palette,
                $versionToPublish->industry,
                $versionToPublish->defaultLocale,
                $versionToPublish->schemaVersion,
                $versionToPublish->siteVersion,
                $versionToPublish->searchTerms,
                $versionToPublish->id,
                $id
            ));
            
            // Update spatial data if coordinates exist
            if ($versionToPublish->geo && $versionToPublish->geo->lat && $versionToPublish->geo->lng) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->table()} 
                     SET location_point = ST_SRID(POINT(%f, %f), 4326) 
                     WHERE id = %s",
                    $versionToPublish->geo->lng, $versionToPublish->geo->lat, $id
                ));
            }
            
            $wpdb->query('COMMIT');
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Create a new draft version from an existing version
     */
    private function createDraftFromVersion(\Minisite\Domain\Entities\Version $sourceVersion, \Minisite\Infrastructure\Persistence\Repositories\VersionRepository $versionRepo): \Minisite\Domain\Entities\Version
    {
        $nextVersion = $versionRepo->getNextVersionNumber($sourceVersion->minisiteId);
        
        $draftVersion = new \Minisite\Domain\Entities\Version(
            id: null,
            minisiteId: $sourceVersion->minisiteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: "Draft from v{$sourceVersion->versionNumber}",
            comment: "Created from version {$sourceVersion->versionNumber} for publishing",
            createdBy: $sourceVersion->createdBy,
            createdAt: null,
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
        
        return $versionRepo->save($draftVersion);
    }

    /**
     * Insert a new minisite
     */
    public function insert(Minisite $m): Minisite
    {
        // Build normalized search_terms (simple example; replace with your builder later)
        $search = trim(strtolower("{$m->name} {$m->city} {$m->industry} {$m->palette} {$m->title}"));

        $data = [
            'id'             => $m->id,
            'slug'           => null, // Will be set later for drafts
            'business_slug'  => $m->slugs->business,
            'location_slug'  => $m->slugs->location,
            'title'          => $m->title,
            'name'           => $m->name,
            'city'           => $m->city,
            'region'         => $m->region,
            'country_code'   => $m->countryCode,
            'postal_code'    => $m->postalCode,
            'site_template'  => $m->siteTemplate,
            'palette'        => $m->palette,
            'industry'       => $m->industry,
            'default_locale' => $m->defaultLocale,
            'schema_version' => $m->schemaVersion,
            'site_version'   => $m->siteVersion,
            'site_json'      => wp_json_encode($m->siteJson),
            'search_terms'   => $search,
            'status'         => $m->status,
            'created_by'     => $m->createdBy,
            'updated_by'     => $m->updatedBy,
        ];

        $result = $this->db->insert($this->table(), $data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d'
        ]);

        if ($result === false) {
            throw new \RuntimeException('Failed to insert minisite.');
        }

        // Sync POINT column if lat/lng set
        if ($m->geo && $m->geo->isSet()) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f,%f),4326) WHERE id = %s",
                $m->geo->lng, $m->geo->lat, $m->id
            ));
        }

        // Return the inserted minisite
        return $this->findById($m->id);
    }

    public function save(Minisite $m, int $expectedSiteVersion): Minisite
    {
        // Build normalized search_terms (simple example; replace with your builder later)
        $search = trim(strtolower("{$m->name} {$m->city} {$m->industry} {$m->palette} {$m->title}"));

        // Update existing by slugs + expected version (optimistic lock)
        $data = [
            'title'          => $m->title,
            'name'           => $m->name,
            'city'           => $m->city,
            'region'         => $m->region,
            'country_code'   => $m->countryCode,
            'postal_code'    => $m->postalCode,
            'site_template'  => $m->siteTemplate,
            'palette'        => $m->palette,
            'industry'       => $m->industry,
            'default_locale' => $m->defaultLocale,
            'schema_version' => $m->schemaVersion,
            'site_json'      => wp_json_encode($m->siteJson),
            'search_terms'   => $search,
            'status'         => $m->status,
            'updated_by'     => $m->updatedBy,
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
            $m->slugs->business,$m->slugs->location,$expectedSiteVersion
        );
        $this->db->query($sql);

        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Concurrent modification detected (optimistic lock failed).');
        }

        // Sync POINT column if lat/lng set
        if ($m->geo && $m->geo->isSet()) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table()} SET location_point = ST_SRID(POINT(%f,%f),4326)
                 WHERE business_slug=%s AND location_slug=%s",
                $m->geo->lng, $m->geo->lat, $m->slugs->business, $m->slugs->location
            ));
        }

        // Re-fetch updated row to return fresh entity (with new site_version)
        $fresh = $this->findBySlugs($m->slugs);
        if (!$fresh) throw new \RuntimeException('Failed to reload minisite after save.');
        return $fresh;
    }

    private function mapRow(array $r): Minisite
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

        return new Minisite(
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

    /**
     * Update minisite title
     */
    public function updateTitle(string $minisiteId, string $title): bool
    {
        $result = $this->db->update(
            $this->table(),
            ['title' => $title],
            ['id' => $minisiteId],
            ['%s'],
            ['%s']
        );
        
        return $result !== false;
    }

    /**
     * Update minisite status
     */
    public function updateStatus(string $minisiteId, string $status): bool
    {
        $result = $this->db->update(
            $this->table(),
            ['status' => $status, 'published_at' => $status === 'published' ? current_time('mysql') : null],
            ['id' => $minisiteId],
            ['%s', '%s'],
            ['%s']
        );
        
        return $result !== false;
    }
}
