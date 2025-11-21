<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteManagement\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Minisite Repository using Doctrine ORM
 *
 * ⚠️ CRITICAL: location_point handling is copied EXACTLY from the old implementation.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 *
 * Note: Naming is agnostic (not "DoctrineMinisiteRepository") since we have
 * only one implementation. If multiple implementations are needed in future
 * (e.g., for testing, caching, or alternative storage), rename to distinguish.
 */
class MinisiteRepository extends EntityRepository implements MinisiteRepositoryInterface
{
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-repository');
    }

    /**
     * Find minisite by slugs
     */
    public function findBySlugs(SlugPair $slugs): ?Minisite
    {
        $this->logger->debug("findBySlugs() entry", array(
            'business_slug' => $slugs->business,
            'location_slug' => $slugs->location,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->where('m.businessSlug = :businessSlug')
                ->andWhere('m.locationSlug = :locationSlug')
                ->setParameter('businessSlug', $slugs->business)
                ->setParameter('locationSlug', $slugs->location)
                ->setMaxResults(1);

            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findBySlugs() exit", array(
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findBySlugs() failed", array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find minisite by ID
     */
    public function findById(string $id): ?Minisite
    {
        $this->logger->debug("findById() entry", array(
            'minisite_id' => $id,
        ));

        try {
            $result = parent::find($id);

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findById() exit", array(
                'minisite_id' => $id,
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findById() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find minisite by individual slug parameters (for race condition checking)
     */
    public function findBySlugParams(string $businessSlug, string $locationSlug): ?Minisite
    {
        $this->logger->debug("findBySlugParams() entry", array(
            'business_slug' => $businessSlug,
            'location_slug' => $locationSlug,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->where('m.businessSlug = :businessSlug')
                ->andWhere('m.locationSlug = :locationSlug')
                ->setParameter('businessSlug', $businessSlug)
                ->setParameter('locationSlug', $locationSlug)
                ->setMaxResults(1);

            // Use FOR UPDATE lock
            $qb->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findBySlugParams() exit", array(
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findBySlugParams() failed", array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * List minisites owned by a user (v1 minimal: uses created_by as owner surrogate)
     * TODO: Switch to explicit owner_user_id column when added.
     *
     * @return Minisite[]
     */
    public function listByOwner(int $userId, int $limit = 50, int $offset = 0): array
    {
        $this->logger->debug("listByOwner() entry", array(
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->where('m.createdBy = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('m.updatedAt', 'DESC')
                ->addOrderBy('m.id', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            $results = $qb->getQuery()->getResult();

            // Load location_point and populate slugs for each result
            foreach ($results as $minisite) {
                $this->loadLocationPoint($minisite);
                $this->populateSlugs($minisite);
            }

            $this->logger->debug("listByOwner() exit", array(
                'user_id' => $userId,
                'count' => count($results),
            ));

            return $results;
        } catch (\Exception $e) {
            $this->logger->error("listByOwner() failed", array(
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Count minisites by owner
     */
    public function countByOwner(int $userId): int
    {
        $this->logger->debug("countByOwner() entry", array(
            'user_id' => $userId,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.createdBy = :userId')
                ->setParameter('userId', $userId);

            $result = (int) $qb->getQuery()->getSingleScalarResult();

            $this->logger->debug("countByOwner() exit", array(
                'user_id' => $userId,
                'count' => $result,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("countByOwner() failed", array(
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Insert a new minisite
     */
    public function insert(Minisite $site): Minisite
    {
        $this->logger->debug("insert() entry", array(
            'minisite_id' => $site->id,
        ));

        try {
            // Build normalized search_terms
            $search = trim(strtolower("{$site->name} {$site->city} {$site->industry} {$site->palette} {$site->title}"));
            $site->searchTerms = $search;

            // Sync slugs to individual columns
            if ($site->slugs !== null) {
                $site->businessSlug = $site->slugs->business;
                $site->locationSlug = $site->slugs->location;
            }

            // Ensure siteJson is a string (entity stores it as string)
            if (is_array($site->siteJson)) { // @phpstan-ignore-line -- defensive check
                $site->setSiteJsonFromArray($site->siteJson);
            }

            // Set default timestamps if not set
            if ($site->createdAt === null) {
                $site->createdAt = new \DateTimeImmutable();
            }
            if ($site->updatedAt === null) {
                $site->updatedAt = new \DateTimeImmutable();
            }

            // Persist and flush
            $this->getEntityManager()->persist($site);
            $this->getEntityManager()->flush();

            // ⚠️ CRITICAL: location_point handling - COPIED EXACTLY from old implementation
            // DO NOT MODIFY THIS LOGIC
            if ($site->geo && $site->geo->isSet()) {
                $connection = $this->getEntityManager()->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = POINT(?, ?) WHERE id = ?",
                    array(
                        $site->geo->getLng(),  // FIRST = longitude
                        $site->geo->getLat(),  // SECOND = latitude
                        $site->id,
                    )
                );
            }

            $this->logger->debug("insert() exit", array(
                'minisite_id' => $site->id,
            ));

            // Return the saved minisite with all data properly loaded from database
            return $this->findById($site->id) ?? $site;
        } catch (\Exception $e) {
            $this->logger->error("insert() failed", array(
                'minisite_id' => $site->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Save live row with optimistic locking.
     *
     * @throws \RuntimeException if version check fails
     */
    public function save(Minisite $m, int $expectedSiteVersion): Minisite
    {
        $this->logger->debug("save() entry", array(
            'minisite_id' => $m->id,
            'expected_site_version' => $expectedSiteVersion,
        ));

        try {
            // Build normalized search_terms
            $search = trim(strtolower("{$m->name} {$m->city} {$m->industry} {$m->palette} {$m->title}"));
            $m->searchTerms = $search;

            // Sync slugs to individual columns
            if ($m->slugs !== null) {
                $m->businessSlug = $m->slugs->business;
                $m->locationSlug = $m->slugs->location;
            }

            // Ensure siteJson is a string (entity stores it as string)
            if (is_array($m->siteJson)) { // @phpstan-ignore-line -- defensive check
                $m->setSiteJsonFromArray($m->siteJson);
            }

            // Update with optimistic locking
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.title', ':title')
                ->set('m.name', ':name')
                ->set('m.city', ':city')
                ->set('m.region', ':region')
                ->set('m.countryCode', ':countryCode')
                ->set('m.postalCode', ':postalCode')
                ->set('m.siteTemplate', ':siteTemplate')
                ->set('m.palette', ':palette')
                ->set('m.industry', ':industry')
                ->set('m.defaultLocale', ':defaultLocale')
                ->set('m.schemaVersion', ':schemaVersion')
                ->set('m.siteJson', ':siteJson')
                ->set('m.searchTerms', ':searchTerms')
                ->set('m.status', ':status')
                ->set('m.updatedBy', ':updatedBy')
                ->set('m.siteVersion', 'm.siteVersion + 1')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.businessSlug = :businessSlug')
                ->andWhere('m.locationSlug = :locationSlug')
                ->andWhere('m.siteVersion = :expectedSiteVersion')
                ->setParameter('title', $m->title)
                ->setParameter('name', $m->name)
                ->setParameter('city', $m->city)
                ->setParameter('region', $m->region)
                ->setParameter('countryCode', $m->countryCode)
                ->setParameter('postalCode', $m->postalCode)
                ->setParameter('siteTemplate', $m->siteTemplate)
                ->setParameter('palette', $m->palette)
                ->setParameter('industry', $m->industry)
                ->setParameter('defaultLocale', $m->defaultLocale)
                ->setParameter('schemaVersion', $m->schemaVersion)
                ->setParameter('siteJson', $m->siteJson)
                ->setParameter('searchTerms', $search)
                ->setParameter('status', $m->status)
                ->setParameter('updatedBy', $m->updatedBy)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('businessSlug', $m->slugs?->business)
                ->setParameter('locationSlug', $m->slugs?->location)
                ->setParameter('expectedSiteVersion', $expectedSiteVersion);

            $rowsAffected = $qb->getQuery()->execute();

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Concurrent modification detected (optimistic lock failed).');
            }

            // ⚠️ CRITICAL: location_point handling - COPIED EXACTLY from old implementation
            // DO NOT MODIFY THIS LOGIC
            if ($m->geo && $m->geo->isSet()) {
                $connection = $this->getEntityManager()->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = POINT(?, ?) "
                    . "WHERE business_slug = ? AND location_slug = ?",
                    array(
                        $m->geo->getLng(),  // FIRST = longitude
                        $m->geo->getLat(),  // SECOND = latitude
                        $m->slugs?->business,
                        $m->slugs?->location,
                    )
                );
            }

            // Re-fetch updated row to return fresh entity (with new site_version)
            $fresh = $this->findBySlugs($m->slugs ?? new SlugPair('', ''));
            if (! $fresh) {
                throw new \RuntimeException('Failed to reload minisite after save.');
            }

            $this->logger->debug("save() exit", array(
                'minisite_id' => $fresh->id,
            ));

            return $fresh;
        } catch (\Exception $e) {
            $this->logger->error("save() failed", array(
                'minisite_id' => $m->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update the slug for a minisite (for draft creation)
     */
    public function updateSlug(string $id, string $slug): void
    {
        $this->logger->debug("updateSlug() entry", array(
            'minisite_id' => $id,
            'slug' => $slug,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.slug', ':slug')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.id = :id')
                ->setParameter('slug', $slug)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('id', $id);

            $rowsAffected = $qb->getQuery()->execute();

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Minisite not found or update failed.');
            }

            $this->logger->debug("updateSlug() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateSlug() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update business and location slugs for a minisite (for publishing)
     */
    public function updateSlugs(string $id, string $businessSlug, string $locationSlug): void
    {
        $this->logger->debug("updateSlugs() entry", array(
            'minisite_id' => $id,
            'business_slug' => $businessSlug,
            'location_slug' => $locationSlug,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.businessSlug', ':businessSlug')
                ->set('m.locationSlug', ':locationSlug')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.id = :id')
                ->setParameter('businessSlug', $businessSlug)
                ->setParameter('locationSlug', $locationSlug)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('id', $id);

            $rowsAffected = $qb->getQuery()->execute();

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Minisite not found or update failed.');
            }

            $this->logger->debug("updateSlugs() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateSlugs() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update the publish status for a minisite
     */
    public function updatePublishStatus(string $id, string $publishStatus): void
    {
        $this->logger->debug("updatePublishStatus() entry", array(
            'minisite_id' => $id,
            'publish_status' => $publishStatus,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.publishStatus', ':publishStatus')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.id = :id')
                ->setParameter('publishStatus', $publishStatus)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('id', $id);

            $rowsAffected = $qb->getQuery()->execute();

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Minisite not found or update failed.');
            }

            $this->logger->debug("updatePublishStatus() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updatePublishStatus() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update the current version ID for a minisite
     */
    public function updateCurrentVersionId(string $id, int $versionId): void
    {
        $this->logger->debug("updateCurrentVersionId() entry", array(
            'minisite_id' => $id,
            'version_id' => $versionId,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.currentVersionId', ':versionId')
                ->where('m.id = :id')
                ->setParameter('versionId', $versionId)
                ->setParameter('id', $id);

            $rowsAffected = $qb->getQuery()->execute();

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Minisite not found or update failed.');
            }

            $this->logger->debug("updateCurrentVersionId() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateCurrentVersionId() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update only coordinates for a minisite (used when saving drafts)
     */
    public function updateCoordinates(string $id, ?float $lat, ?float $lng, int $updatedBy): void
    {
        $this->logger->debug("updateCoordinates() entry", array(
            'minisite_id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'updated_by' => $updatedBy,
        ));

        if ($lat === null || $lng === null) {
            $this->logger->debug("updateCoordinates() - No coordinates provided, skipping", array(
                'minisite_id' => $id,
            ));

            return;
        }

        try {
            // Update updated_by and updated_at via Doctrine
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.updatedBy', ':updatedBy')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.id = :id')
                ->setParameter('updatedBy', $updatedBy)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('id', $id);

            $qb->getQuery()->execute();

            // ⚠️ CRITICAL: location_point handling - COPIED EXACTLY from old implementation
            // DO NOT MODIFY THIS LOGIC
            $connection = $this->getEntityManager()->getConnection();
            $tableName = $this->getClassMetadata()->getTableName();
            $rowsAffected = $connection->executeStatement(
                "UPDATE `{$tableName}` SET location_point = POINT(?, ?) WHERE id = ?",
                array(
                    $lng,  // FIRST = longitude
                    $lat,  // SECOND = latitude
                    $id,
                )
            );

            if ($rowsAffected === 0) {
                throw new \RuntimeException('Minisite not found or update failed.');
            }

            $this->logger->debug("updateCoordinates() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateCoordinates() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update multiple minisite fields in a single operation
     */
    public function updateMinisiteFields(string $minisiteId, array $fields, int $updatedBy): void
    {
        $this->logger->debug("updateMinisiteFields() entry", array(
            'minisite_id' => $minisiteId,
            'fields_count' => count($fields),
            'updated_by' => $updatedBy,
        ));

        try {
            // Check if we have location_point (raw SQL field)
            $hasLocationPoint = isset($fields['location_point']) && strpos($fields['location_point'], 'POINT(') === 0;

            if ($hasLocationPoint) {
                // Handle location_point separately via raw SQL
                $locationPointValue = $fields['location_point'];
                unset($fields['location_point']);

                // Update other fields via Doctrine
                if (! empty($fields)) {
                    $qb = $this->createQueryBuilder('m')
                        ->update()
                        ->set('m.updatedBy', ':updatedBy')
                        ->set('m.updatedAt', ':updatedAt')
                        ->where('m.id = :id')
                        ->setParameter('updatedBy', $updatedBy)
                        ->setParameter('updatedAt', new \DateTimeImmutable())
                        ->setParameter('id', $minisiteId);

                    // Add other fields dynamically
                    foreach ($fields as $field => $value) {
                        $qb->set("m.{$field}", ":{$field}")
                            ->setParameter($field, $value);
                    }

                    $qb->getQuery()->execute();
                }

                // Update location_point via raw SQL
                $connection = $this->getEntityManager()->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = {$locationPointValue} WHERE id = ?",
                    array($minisiteId)
                );
            } else {
                // All fields can be updated via Doctrine
                $qb = $this->createQueryBuilder('m')
                    ->update()
                    ->set('m.updatedBy', ':updatedBy')
                    ->set('m.updatedAt', ':updatedAt')
                    ->where('m.id = :id')
                    ->setParameter('updatedBy', $updatedBy)
                    ->setParameter('updatedAt', new \DateTimeImmutable())
                    ->setParameter('id', $minisiteId);

                // Add fields dynamically
                foreach ($fields as $field => $value) {
                    $qb->set("m.{$field}", ":{$field}")
                        ->setParameter($field, $value);
                }

                $result = $qb->getQuery()->execute();

                if ($result === 0) {
                    throw new \RuntimeException('Failed to update minisite fields.');
                }
            }

            $this->logger->debug("updateMinisiteFields() exit", array(
                'minisite_id' => $minisiteId,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateMinisiteFields() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Publish a minisite using the versioning system
     * Uses latest draft version, with fallback to latest version if no draft exists
     * Does NOT demote published versions - keeps them as published for history
     */
    public function publishMinisite(string $id): void
    {
        $this->logger->debug("publishMinisite() entry", array(
            'minisite_id' => $id,
        ));

        $em = $this->getEntityManager();
        $em->beginTransaction();

        try {
            // Require Doctrine-based VersionRepository from global (initialized by PluginBootstrap)
            if (! isset($GLOBALS['minisite_version_repository'])) {
                throw new \RuntimeException(
                    'VersionRepository not initialized. Ensure PluginBootstrap::initializeConfigSystem() is called.'
                );
            }
            $versionRepo = $GLOBALS['minisite_version_repository'];

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
                $currentPublishedVersion->status = 'draft';
                $currentPublishedVersion->label = 'Archived - ' . ($currentPublishedVersion->label ?? '');
                $versionRepo->save($currentPublishedVersion);
            }

            // Publish the target version
            $versionToPublish->status = 'published';
            $versionToPublish->publishedAt = new \DateTimeImmutable();
            $versionRepo->save($versionToPublish);

            // Update main table with published content
            $minisite = $this->findById($id);
            if (! $minisite) {
                throw new \RuntimeException('Minisite not found.');
            }

            $minisite->siteJson = $versionToPublish->siteJson; // Already a string
            $minisite->title = $versionToPublish->title ?? '';
            $minisite->name = $versionToPublish->name ?? '';
            $minisite->city = $versionToPublish->city ?? '';
            $minisite->region = $versionToPublish->region;
            $minisite->countryCode = $versionToPublish->countryCode ?? '';
            $minisite->postalCode = $versionToPublish->postalCode;
            $minisite->siteTemplate = $versionToPublish->siteTemplate ?? 'v2025';
            $minisite->palette = $versionToPublish->palette ?? 'blue';
            $minisite->industry = $versionToPublish->industry ?? 'services';
            $minisite->defaultLocale = $versionToPublish->defaultLocale ?? 'en-US';
            $minisite->schemaVersion = $versionToPublish->schemaVersion ?? 1;
            $minisite->siteVersion = $versionToPublish->siteVersion ?? 1;
            $minisite->searchTerms = $versionToPublish->searchTerms;
            $minisite->status = 'published';
            $minisite->publishStatus = 'published';
            $minisite->currentVersionId = $versionToPublish->id;
            $minisite->updatedAt = new \DateTimeImmutable();

            $em->persist($minisite);
            $em->flush();

            // Update spatial data if coordinates exist
            if ($versionToPublish->geo && $versionToPublish->geo->isSet()) {
                $connection = $em->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = POINT(?, ?) WHERE id = ?",
                    array(
                        $versionToPublish->geo->getLng(),
                        $versionToPublish->geo->getLat(),
                        $id,
                    )
                );
            }

            $em->commit();

            $this->logger->debug("publishMinisite() exit", array(
                'minisite_id' => $id,
            ));
        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error("publishMinisite() failed", array(
                'minisite_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Update minisite title
     */
    public function updateTitle(string $minisiteId, string $title): bool
    {
        $this->logger->debug("updateTitle() entry", array(
            'minisite_id' => $minisiteId,
            'title' => $title,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.title', ':title')
                ->where('m.id = :id')
                ->setParameter('title', $title)
                ->setParameter('id', $minisiteId);

            $result = $qb->getQuery()->execute();

            $this->logger->debug("updateTitle() exit", array(
                'minisite_id' => $minisiteId,
                'success' => $result !== false,
            ));

            return $result !== false;
        } catch (\Exception $e) {
            $this->logger->error("updateTitle() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            return false;
        }
    }

    /**
     * Update minisite status
     */
    public function updateStatus(string $minisiteId, string $status): bool
    {
        $this->logger->debug("updateStatus() entry", array(
            'minisite_id' => $minisiteId,
            'status' => $status,
        ));

        try {
            $publishedAt = $status === 'published' ? new \DateTimeImmutable() : null;

            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.status', ':status')
                ->set('m.publishedAt', ':publishedAt')
                ->where('m.id = :id')
                ->setParameter('status', $status)
                ->setParameter('publishedAt', $publishedAt)
                ->setParameter('id', $minisiteId);

            $result = $qb->getQuery()->execute();

            $this->logger->debug("updateStatus() exit", array(
                'minisite_id' => $minisiteId,
                'success' => $result !== false,
            ));

            return $result !== false;
        } catch (\Exception $e) {
            $this->logger->error("updateStatus() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            return false;
        }
    }

    /**
     * Update business info fields in main minisite table
     */
    public function updateBusinessInfo(string $minisiteId, array $fields, int $updatedBy): void
    {
        $this->logger->debug("updateBusinessInfo() entry", array(
            'minisite_id' => $minisiteId,
            'fields_count' => count($fields),
            'updated_by' => $updatedBy,
        ));

        try {
            $qb = $this->createQueryBuilder('m')
                ->update()
                ->set('m.updatedBy', ':updatedBy')
                ->set('m.updatedAt', ':updatedAt')
                ->where('m.id = :id')
                ->setParameter('updatedBy', $updatedBy)
                ->setParameter('updatedAt', new \DateTimeImmutable())
                ->setParameter('id', $minisiteId);

            // Add business info fields dynamically
            foreach ($fields as $field => $value) {
                $qb->set("m.{$field}", ":{$field}")
                    ->setParameter($field, $value);
            }

            $result = $qb->getQuery()->execute();

            if ($result === 0) {
                throw new \RuntimeException('Failed to update business info fields.');
            }

            $this->logger->debug("updateBusinessInfo() exit", array(
                'minisite_id' => $minisiteId,
            ));
        } catch (\Exception $e) {
            $this->logger->error("updateBusinessInfo() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Load location_point from database and populate geo property
     *
     * ⚠️ CRITICAL: This method is copied EXACTLY from VersionRepository.
     * DO NOT modify this logic.
     */
    private function loadLocationPoint(Minisite $minisite): void
    {
        if (empty($minisite->id)) {
            return;
        }

        try {
            $connection = $this->getEntityManager()->getConnection();
            $tableName = $this->getClassMetadata()->getTableName();

            // Extract lat/lng from POINT geometry
            // POINT is stored as POINT(lng, lat), so ST_X() returns lng and ST_Y() returns lat
            $pointResult = $connection->fetchAssociative(
                "SELECT ST_X(location_point) as lng, ST_Y(location_point) as lat
                 FROM `{$tableName}` WHERE id = ?",
                array($minisite->id)
            );

            if ($pointResult && $pointResult['lat'] !== null && $pointResult['lng'] !== null) {
                $minisite->geo = new GeoPoint(
                    lat: (float) $pointResult['lat'],  // ST_Y() = latitude
                    lng: (float) $pointResult['lng']   // ST_X() = longitude
                );
            }
        } catch (\Exception $e) {
            // If spatial functions fail, geo remains null
            // This is expected if MySQL spatial functions are not available (e.g., in unit tests)
            $this->logger->debug("loadLocationPoint() - spatial functions not available", array(
                'minisite_id' => $minisite->id,
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Populate slugs property from business_slug and location_slug columns
     */
    private function populateSlugs(Minisite $minisite): void
    {
        if ($minisite->businessSlug !== null && $minisite->locationSlug !== null) {
            $minisite->slugs = new SlugPair(
                business: $minisite->businessSlug,
                location: $minisite->locationSlug
            );
        }
    }
}
