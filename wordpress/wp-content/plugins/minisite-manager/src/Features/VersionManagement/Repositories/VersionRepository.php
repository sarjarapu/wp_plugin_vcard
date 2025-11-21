<?php

declare(strict_types=1);

namespace Minisite\Features\VersionManagement\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Version Repository using Doctrine ORM
 *
 * ⚠️ CRITICAL: location_point handling is copied EXACTLY from the old implementation.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 *
 * Note: Naming is agnostic (not "DoctrineVersionRepository") since we have
 * only one implementation. If multiple implementations are needed in future
 * (e.g., for testing, caching, or alternative storage), rename to distinguish.
 */
class VersionRepository extends EntityRepository implements VersionRepositoryInterface
{
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->logger = LoggingServiceProvider::getFeatureLogger('version-repository');
    }

    /**
     * Save version (insert or update)
     *
     * ⚠️ CRITICAL: location_point handling is copied EXACTLY from old implementation.
     * DO NOT modify this logic.
     */
    public function save(Version $version): Version
    {
        $this->logger->debug("save() entry", array(
            'version_id' => $version->id,
            'minisite_id' => $version->minisiteId,
            'version_number' => $version->versionNumber,
            'status' => $version->status,
        ));

        try {
            // Sync slugs to individual columns
            if ($version->slugs !== null) {
                $version->businessSlug = $version->slugs->business;
                $version->locationSlug = $version->slugs->location;
            }

            // Ensure siteJson is a string (entity stores it as string)
            // Note: siteJson property is typed as string, so this check is for type safety
            // If somehow an array was assigned, convert it to JSON string
            if (is_array($version->siteJson)) { // @phpstan-ignore-line -- defensive check for edge cases
                $version->setSiteJsonFromArray($version->siteJson);
            }

            // Set default createdAt if not set
            if ($version->createdAt === null) {
                $version->createdAt = new \DateTimeImmutable();
            }

            // Persist and flush
            $this->getEntityManager()->persist($version);
            $this->getEntityManager()->flush();

            // ⚠️ CRITICAL: location_point handling - COPIED EXACTLY from old implementation
            // DO NOT MODIFY THIS LOGIC
            // See: docs/issues/location-point-lessons-learned.md
            // Original code: src/Infrastructure/Persistence/Repositories/VersionRepository.php lines 83-131
            if ($version->geo && $version->geo->isSet()) {
                $connection = $this->getEntityManager()->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $result = $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = POINT(?, ?) WHERE id = ?",
                    array(
                        $version->geo->getLng(),  // FIRST = longitude
                        $version->geo->getLat(),  // SECOND = latitude
                        $version->id,
                    )
                );

                // Debug: Check if the update was successful
                if ($result === 0) {
                    $this->logger->error('Failed to update location_point for version', array(
                        'version_id' => $version->id,
                    ));
                }
            } else {
                // Clear location_point if no geo data
                $connection = $this->getEntityManager()->getConnection();
                $tableName = $this->getClassMetadata()->getTableName();
                $connection->executeStatement(
                    "UPDATE `{$tableName}` SET location_point = NULL WHERE id = ?",
                    array($version->id)
                );
            }

            $this->logger->debug("save() exit", array(
                'version_id' => $version->id,
                'minisite_id' => $version->minisiteId,
            ));

            // Return the saved version with all data properly loaded from database
            return $this->findById($version->id) ?? $version;
        } catch (\Exception $e) {
            $this->logger->error("save() failed", array(
                'version_id' => $version->id,
                'minisite_id' => $version->minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find version by ID
     *
     * Note: This method signature matches Doctrine's EntityRepository::find() to avoid conflicts.
     * For type-safe usage, use findById() which is defined in the interface.
     */
    public function find(
        mixed $id,
        \Doctrine\DBAL\LockMode|int|null $lockMode = null,
        ?int $lockVersion = null
    ): ?Version {
        if (! is_int($id)) {
            throw new \InvalidArgumentException('Version ID must be an integer');
        }

        $this->logger->debug("find() entry", array(
            'version_id' => $id,
        ));

        try {
            // Call parent::find() to use Doctrine's implementation with locking support
            $result = parent::find($id, $lockMode, $lockVersion);

            if ($result !== null) {
                // Load location_point and populate geo property
                $this->loadLocationPoint($result);
                // Populate slugs from columns
                $this->populateSlugs($result);
            }

            $this->logger->debug("find() exit", array(
                'version_id' => $id,
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("find() failed", array(
                'version_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Type-safe find by ID (delegates to find())
     */
    public function findById(int $id): ?Version
    {
        return $this->find($id);
    }

    /**
     * Find versions by minisite ID
     *
     * @return Version[]
     */
    public function findByMinisiteId(string $minisiteId, int $limit = 50, int $offset = 0): array
    {
        $this->logger->debug("findByMinisiteId() entry", array(
            'minisite_id' => $minisiteId,
            'limit' => $limit,
            'offset' => $offset,
        ));

        try {
            $qb = $this->createQueryBuilder('v')
                ->where('v.minisiteId = :minisiteId')
                ->setParameter('minisiteId', $minisiteId)
                ->orderBy('v.versionNumber', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            $results = $qb->getQuery()->getResult();

            // Load location_point and populate slugs for each result
            foreach ($results as $version) {
                $this->loadLocationPoint($version);
                $this->populateSlugs($version);
            }

            $this->logger->debug("findByMinisiteId() exit", array(
                'minisite_id' => $minisiteId,
                'count' => count($results),
            ));

            return $results;
        } catch (\Exception $e) {
            $this->logger->error("findByMinisiteId() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find latest version for a minisite
     */
    public function findLatestVersion(string $minisiteId): ?Version
    {
        $this->logger->debug("findLatestVersion() entry", array(
            'minisite_id' => $minisiteId,
        ));

        try {
            $qb = $this->createQueryBuilder('v')
                ->where('v.minisiteId = :minisiteId')
                ->setParameter('minisiteId', $minisiteId)
                ->orderBy('v.versionNumber', 'DESC')
                ->setMaxResults(1);

            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findLatestVersion() exit", array(
                'minisite_id' => $minisiteId,
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findLatestVersion() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Find latest draft version for a minisite
     */
    public function findLatestDraft(string $minisiteId): ?Version
    {
        $this->logger->debug("findLatestDraft() entry", array(
            'minisite_id' => $minisiteId,
        ));

        try {
            $qb = $this->createQueryBuilder('v')
                ->where('v.minisiteId = :minisiteId')
                ->andWhere("v.status = 'draft'")
                ->setParameter('minisiteId', $minisiteId)
                ->orderBy('v.versionNumber', 'DESC')
                ->setMaxResults(1);

            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findLatestDraft() exit", array(
                'minisite_id' => $minisiteId,
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findLatestDraft() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
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

        // Create new version entity
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
            siteJson: $sourceVersion->getSiteJsonAsArray(), // Get as array for constructor
            // Copy all minisite fields
            slugs: $sourceVersion->getSlugs(),
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

    /**
     * Find published version for a minisite
     */
    public function findPublishedVersion(string $minisiteId): ?Version
    {
        $this->logger->debug("findPublishedVersion() entry", array(
            'minisite_id' => $minisiteId,
        ));

        try {
            $qb = $this->createQueryBuilder('v')
                ->where('v.minisiteId = :minisiteId')
                ->andWhere("v.status = 'published'")
                ->setParameter('minisiteId', $minisiteId)
                ->setMaxResults(1);

            $result = $qb->getQuery()->getOneOrNullResult();

            if ($result !== null) {
                $this->loadLocationPoint($result);
                $this->populateSlugs($result);
            }

            $this->logger->debug("findPublishedVersion() exit", array(
                'minisite_id' => $minisiteId,
                'found' => $result !== null,
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("findPublishedVersion() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Get next version number for a minisite
     */
    public function getNextVersionNumber(string $minisiteId): int
    {
        $this->logger->debug("getNextVersionNumber() entry", array(
            'minisite_id' => $minisiteId,
        ));

        try {
            $qb = $this->createQueryBuilder('v')
                ->select('MAX(v.versionNumber) as max_version')
                ->where('v.minisiteId = :minisiteId')
                ->setParameter('minisiteId', $minisiteId);

            $result = $qb->getQuery()->getSingleScalarResult();

            $nextVersion = $result ? (int) $result + 1 : 1;

            $this->logger->debug("getNextVersionNumber() exit", array(
                'minisite_id' => $minisiteId,
                'next_version' => $nextVersion,
            ));

            return $nextVersion;
        } catch (\Exception $e) {
            $this->logger->error("getNextVersionNumber() failed", array(
                'minisite_id' => $minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Delete version
     */
    public function delete(int $id): bool
    {
        $this->logger->debug("delete() entry", array(
            'version_id' => $id,
        ));

        try {
            $version = $this->findById($id);
            if ($version === null) {
                $this->logger->warning("delete() - version not found", array(
                    'version_id' => $id,
                ));

                return false;
            }

            $this->getEntityManager()->remove($version);
            $this->getEntityManager()->flush();

            $this->logger->debug("delete() exit", array(
                'version_id' => $id,
                'deleted' => true,
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error("delete() failed", array(
                'version_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * ⚠️ CRITICAL: Load location_point from database and populate geo property
     * This is copied EXACTLY from the old implementation.
     * DO NOT MODIFY THIS LOGIC.
     * See: docs/issues/location-point-lessons-learned.md
     * Original code: src/Infrastructure/Persistence/Repositories/VersionRepository.php lines 302-328
     */
    private function loadLocationPoint(Version $version): void
    {
        if ($version->id === null) {
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
                array($version->id)
            );

            if ($pointResult && $pointResult['lat'] !== null && $pointResult['lng'] !== null) {
                $version->geo = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint(
                    lat: (float) $pointResult['lat'],  // ST_Y() = latitude
                    lng: (float) $pointResult['lng']   // ST_X() = longitude
                );
            }
        } catch (\Exception $e) {
            // If spatial functions fail, geo remains null
            // This is expected if MySQL spatial functions are not available (e.g., in unit tests)
            $this->logger->debug("loadLocationPoint() - spatial functions not available", array(
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Populate slugs property from business_slug and location_slug columns
     */
    private function populateSlugs(Version $version): void
    {
        if ($version->businessSlug !== null && $version->locationSlug !== null) {
            $version->slugs = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair(
                business: $version->businessSlug,
                location: $version->locationSlug
            );
        }
    }
}
