<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

class MinisiteRepository implements MinisiteRepositoryInterface
{
    private LoggerInterface $logger;

    public function __construct(private \wpdb $db)
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-repository');
    }

    private function table(): string
    {
        return $this->db->prefix . 'minisites';
    }

    public function findBySlugs(SlugPair $slugs): ?Minisite
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE business_slug=%s AND location_slug=%s LIMIT 1",
            $slugs->business,
            $slugs->location
        );
        $row = $this->db->get_row($sql, ARRAY_A);
        if (! $row) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * Find minisite by individual slug parameters (for race condition checking)
     */
    public function findBySlugParams(string $businessSlug, string $locationSlug): ?Minisite
    {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE business_slug=%s AND location_slug=%s LIMIT 1 FOR UPDATE",
            $businessSlug,
            $locationSlug
        );
        $row = $this->db->get_row($sql, ARRAY_A);
        if (! $row) {
            return null;
        }

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
        $sql  = $this->db->prepare(
            "SELECT * FROM {$this->table()} WHERE created_by=%d ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            $userId,
            $limit,
            $offset
        );
        $rows = $this->db->get_results($sql, ARRAY_A) ?: array();
        return array_map(fn($r) => $this->mapRow($r), $rows);
    }

    /**
     * Count minisites by owner
     */
    public function countByOwner(int $userId): int
    {
        $sql = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE created_by=%d",
            $userId
        );
        return (int) $this->db->get_var($sql);
    }

    /**
     * Find minisite by ID
     */
    public function findById(string $id): ?Minisite
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table()} WHERE id=%s LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);
        if (! $row) {
            return null;
        }
        return $this->mapRow($row);
    }


    /**
     * Update the current version ID for a minisite
     */
    public function updateCurrentVersionId(string $id, int $versionId): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET _minisite_current_version_id = %d WHERE id = %s",
            $versionId,
            $id
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
        $this->logger->debug('MinisiteRepository::updateCoordinates() - Input', [
            'minisite_id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'updated_by' => $updatedBy,
            'operation_type' => 'input'
        ]);

        if ($lat === null || $lng === null) {
            $this->logger->debug('MinisiteRepository::updateCoordinates() - No coordinates provided, skipping', [
                'minisite_id' => $id,
                'operation_type' => 'skipped'
            ]);
            return;
        }

        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET updated_by = %d, updated_at = NOW(), " .
            "location_point = POINT(%f, %f) WHERE id = %s",
            $updatedBy,
            $lng,
            $lat,
            $id
        );

        $this->logger->debug('MinisiteRepository::updateCoordinates() - Executing SQL', [
            'minisite_id' => $id,
            'sql_query' => $sql,
            'operation_type' => 'sql_execution'
        ]);

        $rows_affected = $this->db->query($sql);

        $this->logger->debug('MinisiteRepository::updateCoordinates() - Output', [
            'minisite_id' => $id,
            'rows_affected' => $rows_affected,
            'last_error' => $this->db->last_error,
            'operation_type' => 'output'
        ]);

        if ($rows_affected === 0) {
            $this->logger->error('MinisiteRepository::updateCoordinates() - Error', [
                'minisite_id' => $id,
                'error_message' => 'Minisite not found or update failed',
                'operation_type' => 'error'
            ]);
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Update multiple minisite fields in a single operation
     */
    public function updateMinisiteFields(string $minisiteId, array $fields, int $updatedBy): void
    {
        $this->logger->debug('MinisiteRepository::updateMinisiteFields() - Input', [
            'minisite_id' => $minisiteId,
            'fields_count' => count($fields),
            'updated_by' => $updatedBy,
            'operation_type' => 'input'
        ]);

        $updateFields = array(
            'updated_by' => $updatedBy,
            'updated_at' => current_time('mysql'),
        );

        $formatFields = array( '%d', '%s' );

        // Add the provided fields
        foreach ($fields as $field => $value) {
            if ($field === 'location_point' && strpos($value, 'POINT(') === 0) {
                // Handle POINT field specially - don't escape it
                $updateFields[$field] = $value;
                $formatFields[] = '%s'; // This will be ignored for raw SQL
            } else {
                $updateFields[$field] = $value;
                $formatFields[] = '%s';
            }
        }

        // Check if we have raw SQL fields (like POINT)
        $hasRawSql = false;
        foreach ($fields as $field => $value) {
            if ($field === 'location_point' && strpos($value, 'POINT(') === 0) {
                $hasRawSql = true;
                break;
            }
        }

        if ($hasRawSql) {
            // Build custom SQL for raw fields
            $setParts = [];
            foreach ($updateFields as $field => $value) {
                if ($field === 'location_point' && strpos($value, 'POINT(') === 0) {
                    $setParts[] = "`$field` = $value";
                } else {
                    $setParts[] = "`$field` = %s";
                }
            }

            $sql = "UPDATE {$this->table()} SET " . implode(', ', $setParts) . " WHERE id = %s";
            $values = array_values(array_filter($updateFields, function ($value, $field) {
                return !($field === 'location_point' && strpos($value, 'POINT(') === 0);
            }, ARRAY_FILTER_USE_BOTH));
            $values[] = $minisiteId;

            $preparedSql = $this->db->prepare($sql, ...$values);
            $result = $this->db->query($preparedSql);
        } else {
            $result = $this->db->update(
                $this->table(),
                $updateFields,
                array( 'id' => $minisiteId ),
                $formatFields,
                array( '%s' )
            );
        }

        $this->logger->debug('MinisiteRepository::updateMinisiteFields() - Output', [
            'minisite_id' => $minisiteId,
            'result' => $result,
            'last_error' => $this->db->last_error,
            'operation_type' => 'output'
        ]);

        if ($result === false) {
            $this->logger->error('MinisiteRepository::updateMinisiteFields() - Error', [
                'minisite_id' => $minisiteId,
                'error_message' => 'Failed to update minisite fields',
                'operation_type' => 'error'
            ]);
            throw new \RuntimeException('Failed to update minisite fields.');
        }
    }

    /**
     * Update the slug for a minisite (for draft creation)
     */
    public function updateSlug(string $id, string $slug): void
    {
        $sql = $this->db->prepare(
            "UPDATE {$this->table()} SET slug=%s, updated_at=NOW() WHERE id=%s",
            $slug,
            $id
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
            $businessSlug,
            $locationSlug,
            $id
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
            $publishStatus,
            $id
        );
        $this->db->query($sql);

        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Minisite not found or update failed.');
        }
    }

    /**
     * Publish a minisite using the versioning system
     * Uses latest draft version, with fallback to latest version if no draft exists
     * Does NOT demote published versions - keeps them as published for history
     */
    public function publishMinisite(string $id): void
    {
        $this->db->query('START TRANSACTION');

        try {
            $versionRepo = new \Minisite\Infrastructure\Persistence\Repositories\VersionRepository($this->db);

            // Try to find latest draft first (preferred)
            $versionToPublish = $versionRepo->findLatestDraft($id);

            if (! $versionToPublish) {
                // Fallback: find latest version (could be published)
                $latestVersion = $versionRepo->findLatestVersion($id);

                if (! $latestVersion) {
                    throw new \RuntimeException('No version found for minisite.');
                }

                // If latest version is already published, create a new draft from it
                if ($latestVersion->status === 'published') {
                    $versionToPublish = $versionRepo->createDraftFromVersion($latestVersion);
                } else {
                    $versionToPublish = $latestVersion;
                }
            }

            // Demote current published version to draft (for history)
            // Only if there's a current published version
            $currentPublishedVersion = $versionRepo->findPublishedVersion($id);
            if ($currentPublishedVersion) {
                $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->db->prefix}minisite_versions 
                     SET status = 'draft', label = CONCAT('Archived - ', label)
                     WHERE id = %d",
                        $currentPublishedVersion->id
                    )
                );
            }

            // Publish the target version
            $this->db->query(
                $this->db->prepare(
                    "UPDATE {$this->db->prefix}minisite_versions 
                 SET status = 'published', published_at = NOW() 
                 WHERE id = %d",
                    $versionToPublish->id
                )
            );

            // Update main table with published content
            $updateData = array(
                'site_json'                    => wp_json_encode($versionToPublish->siteJson),
                'title'                        => $versionToPublish->title,
                'name'                         => $versionToPublish->name,
                'city'                         => $versionToPublish->city,
                'region'                       => $versionToPublish->region,
                'country_code'                 => $versionToPublish->countryCode,
                'postal_code'                  => $versionToPublish->postalCode,
                'site_template'                => $versionToPublish->siteTemplate,
                'palette'                      => $versionToPublish->palette,
                'industry'                     => $versionToPublish->industry,
                'default_locale'               => $versionToPublish->defaultLocale,
                'schema_version'               => $versionToPublish->schemaVersion,
                'site_version'                 => $versionToPublish->siteVersion,
                'search_terms'                 => $versionToPublish->searchTerms,
                'status'                       => 'published',
                'publish_status'               => 'published',
                '_minisite_current_version_id' => $versionToPublish->id,
                'updated_at'                   => date('Y-m-d H:i:s'),
            );

            $updateResult = $this->db->update(
                $this->table(),
                $updateData,
                array( 'id' => $id )
            );

            // Update spatial data if coordinates exist
            if ($versionToPublish->geo && $versionToPublish->geo->getLat() && $versionToPublish->geo->getLng()) {
                $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->table()} 
                     SET location_point = POINT(%f, %f) 
                     WHERE id = %s",
                        $versionToPublish->geo->getLng(),
                        $versionToPublish->geo->getLat(),
                        $id
                    )
                );
            }

            $this->db->query('COMMIT');
        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }


    /**
     * Insert a new minisite
     */
    public function insert(Minisite $site): Minisite
    {
        // Build normalized search_terms (simple example; replace with your builder later)
        $search = trim(strtolower("{$site->name} {$site->city} {$site->industry} {$site->palette} {$site->title}"));

        $data = array(
            'id'             => $site->id,
            'slug'           => $site->slug,
            'business_slug'  => $site->slugs->business,
            'location_slug'  => $site->slugs->location,
            'title'          => $site->title,
            'name'           => $site->name,
            'city'           => $site->city,
            'region'         => $site->region,
            'country_code'   => $site->countryCode,
            'postal_code'    => $site->postalCode,
            'site_template'  => $site->siteTemplate,
            'palette'        => $site->palette,
            'industry'       => $site->industry,
            'default_locale' => $site->defaultLocale,
            'schema_version' => $site->schemaVersion,
            'site_version'   => $site->siteVersion,
            'site_json'      => wp_json_encode($site->siteJson),
            'search_terms'   => $search,
            'status'         => $site->status,
            'publish_status' => $site->status, // Set publish_status to same as status initially
            'created_at'     => $site->createdAt->format('Y-m-d H:i:s'),
            'updated_at'     => $site->updatedAt->format('Y-m-d H:i:s'),
            'published_at'   => $site->publishedAt?->format('Y-m-d H:i:s'),
            'created_by'     => $site->createdBy,
            'updated_by'     => $site->updatedBy,
        );

        $result = $this->db->insert(
            $this->table(),
            $data,
            array(
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
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
            )
        );

        // $result is int|false - number of affected rows on success, false on failure
        if ($result === false) {
            throw new \RuntimeException('Failed to insert minisite.');
        }

        // Update location_point if geo data is available
        if ($site->geo && $site->geo->isSet()) {
            $this->db->query(
                $this->db->prepare(
                    "UPDATE {$this->table()} SET location_point = POINT(%f,%f) WHERE id = %s",
                    $site->geo->getLng(),
                    $site->geo->getLat(),
                    $site->id
                )
            );
        }

        // Return the inserted minisite
        return $this->findById($site->id);
    }

    public function save(Minisite $m, int $expectedSiteVersion): Minisite
    {
        // Build normalized search_terms (simple example; replace with your builder later)
        $search = trim(strtolower("{$m->name} {$m->city} {$m->industry} {$m->palette} {$m->title}"));

        // Update existing by slugs + expected version (optimistic lock)
        $data   = array(
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
        );
        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' );

        // If row exists, update w/ optimistic lock
        $sql = $this->db->prepare(
            "UPDATE {$this->table()}
             SET title=%s,name=%s,city=%s,region=%s,country_code=%s,postal_code=%s,
                 site_template=%s,palette=%s,industry=%s,default_locale=%s,
                 schema_version=%d,site_json=%s,search_terms=%s,status=%s,updated_by=%d,
                 site_version = site_version + 1
             WHERE business_slug=%s AND location_slug=%s AND site_version=%d",
            $data['title'],
            $data['name'],
            $data['city'],
            $data['region'],
            $data['country_code'],
            $data['postal_code'],
            $data['site_template'],
            $data['palette'],
            $data['industry'],
            $data['default_locale'],
            $data['schema_version'],
            $data['site_json'],
            $data['search_terms'],
            $data['status'],
            $data['updated_by'],
            $m->slugs->business,
            $m->slugs->location,
            $expectedSiteVersion
        );
        $this->db->query($sql);

        if ($this->db->rows_affected === 0) {
            throw new \RuntimeException('Concurrent modification detected (optimistic lock failed).');
        }

        // Sync POINT column if lat/lng set
        if ($m->geo && $m->geo->isSet()) {
            $this->db->query(
                $this->db->prepare(
                    "UPDATE {$this->table()} SET location_point = POINT(%f,%f)
                 WHERE business_slug=%s AND location_slug=%s",
                    $m->geo->getLng(),
                    $m->geo->getLat(),
                    $m->slugs->business,
                    $m->slugs->location
                )
            );
        }

        // Re-fetch updated row to return fresh entity (with new site_version)
        $fresh = $this->findBySlugs($m->slugs);
        if (! $fresh) {
            throw new \RuntimeException('Failed to reload minisite after save.');
        }
        return $fresh;
    }

    private function mapRow(array $r): Minisite
    {
        // Extract lat/lng from location_point geometry
        $geo = null;
        if (! empty($r['location_point'])) {
            // POINT is stored as POINT(lng, lat), so ST_X() returns lng and ST_Y() returns lat
            $pointResult = $this->db->get_row(
                $this->db->prepare(
                    "SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat " .
                    "FROM {$this->table()} WHERE id = %s",
                    $r['id']
                ),
                ARRAY_A
            );

            if ($pointResult && ($pointResult['lat'] ?? null) && ($pointResult['lng'] ?? null)) {
                $geo = new GeoPoint(
                    lat: (float) ($pointResult['lat'] ?? 0),
                    lng: (float) ($pointResult['lng'] ?? 0)
                );
            }
        }

        return new Minisite(
            id:            (string) $r['id'],
            slug:          $r['slug'] ?: null,
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
            schemaVersion: (int) $r['schema_version'],
            siteVersion:   (int) $r['site_version'],
            siteJson:      json_decode((string) $r['site_json'], true) ?: array(),
            searchTerms:   $r['search_terms'] ?: null,
            status:        $r['status'],
            publishStatus: $r['publish_status'],
            createdAt:     $r['created_at'] ? new \DateTimeImmutable($r['created_at']) : null,
            updatedAt:     $r['updated_at'] ? new \DateTimeImmutable($r['updated_at']) : null,
            publishedAt:   $r['published_at'] ? new \DateTimeImmutable($r['published_at']) : null,
            createdBy:     $r['created_by'] ? (int) $r['created_by'] : null,
            updatedBy:     $r['updated_by'] ? (int) $r['updated_by'] : null,
            currentVersionId: $r['_minisite_current_version_id'] ? (int) $r['_minisite_current_version_id'] : null,
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
            array( 'title' => $title ),
            array( 'id' => $minisiteId ),
            array( '%s' ),
            array( '%s' )
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
            array(
                'status'       => $status,
                'published_at' => $status === 'published' ? current_time('mysql') : null,
            ),
            array( 'id' => $minisiteId ),
            array( '%s', '%s' ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Update business info fields in main minisite table
     */
    public function updateBusinessInfo(string $minisiteId, array $fields, int $updatedBy): void
    {
        $updateFields = array(
            'updated_by' => $updatedBy,
            'updated_at' => current_time('mysql'),
        );

        $formatFields = array( '%d', '%s' );

        // Add the business info fields
        foreach ($fields as $field => $value) {
            $updateFields[ $field ] = $value;
            $formatFields[]         = '%s';
        }

        $result = $this->db->update(
            $this->table(),
            $updateFields,
            array( 'id' => $minisiteId ),
            $formatFields,
            array( '%s' )
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to update business info fields.');
        }
    }
}
