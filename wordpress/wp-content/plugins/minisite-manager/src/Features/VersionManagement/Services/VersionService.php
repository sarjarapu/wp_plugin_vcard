<?php

namespace Minisite\Features\VersionManagement\Services;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * Service for managing minisite versions
 */
class VersionService
{
    public function __construct(
        private MinisiteRepositoryInterface $minisiteRepository,
        private VersionRepositoryInterface $versionRepository,
        private WordPressVersionManager $wordPressManager
    ) {
    }

    /**
     * List all versions for a minisite
     */
    public function listVersions(ListVersionsCommand $command): array
    {
        // Verify minisite exists and user has access
        $minisite = $this->minisiteRepository->findById($command->siteId);
        if (! $minisite) {
            throw new \Exception('Minisite not found');
        }

        if ($minisite->createdBy !== $command->userId) {
            throw new \Exception('Access denied');
        }

        return $this->versionRepository->findByMinisiteId($command->siteId);
    }

    /**
     * Create a new draft version
     */
    public function createDraft(CreateDraftCommand $command): Version
    {
        // Verify minisite exists and user has access
        $minisite = $this->minisiteRepository->findById($command->siteId);
        if (! $minisite) {
            throw new \Exception('Minisite not found');
        }

        if ($minisite->createdBy !== $command->userId) {
            throw new \Exception('Access denied');
        }

        $nextVersion = $this->versionRepository->getNextVersionNumber($command->siteId);

        $version = new Version(
            id: null,
            minisiteId: $command->siteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: $command->label,
            comment: $command->comment,
            createdBy: $command->userId,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: null,
            siteJson: $command->siteJson
        );

        return $this->versionRepository->save($version);
    }

    /**
     * Publish a version (atomic operation)
     */
    public function publishVersion(PublishVersionCommand $command): void
    {
        // Verify minisite exists and user has access
        $minisite = $this->minisiteRepository->findById($command->siteId);
        if (! $minisite) {
            throw new \Exception('Minisite not found');
        }

        if ($minisite->createdBy !== $command->userId) {
            throw new \Exception('Access denied');
        }

        $version = $this->versionRepository->findById($command->versionId);
        if (! $version || $version->minisiteId !== $command->siteId) {
            throw new \Exception('Version not found');
        }

        if ($version->status !== 'draft') {
            throw new \Exception('Only draft versions can be published');
        }

        $this->performPublishVersion($command->siteId, $command->versionId, $version);
    }

    /**
     * Create a rollback version from a source version
     */
    public function createRollbackVersion(RollbackVersionCommand $command): Version
    {
        // Verify minisite exists and user has access
        $minisite = $this->minisiteRepository->findById($command->siteId);
        if (! $minisite) {
            throw new \Exception('Minisite not found');
        }

        if ($minisite->createdBy !== $command->userId) {
            throw new \Exception('Access denied');
        }

        $sourceVersion = $this->versionRepository->findById($command->sourceVersionId);
        if (! $sourceVersion || $sourceVersion->minisiteId !== $command->siteId) {
            throw new \Exception('Source version not found');
        }

        return $this->performCreateRollbackVersion($command->siteId, $command->sourceVersionId, $command->userId);
    }

    /**
     * Perform the actual version publishing (atomic operation)
     */
    private function performPublishVersion(
        string $minisiteId,
        int $versionId,
        Version $version
    ): void {
        global $wpdb;

        db::query('START TRANSACTION');

        try {
            // Move current published version to draft
            db::query(
                "UPDATE {$wpdb->prefix}minisite_versions
                 SET status = 'draft'
                 WHERE minisite_id = %s AND status = 'published'",
                array($minisiteId)
            );

            // Publish new version
            db::query(
                "UPDATE {$wpdb->prefix}minisite_versions SET status = 'published', published_at = NOW() WHERE id = %d",
                array($versionId)
            );

            // Update profile with published version data and current version ID
            db::query(
                "UPDATE {$wpdb->prefix}minisites
                 SET site_json = %s, title = %s, name = %s, city = %s, region = %s,
                     country_code = %s, postal_code = %s, site_template = %s, palette = %s,
                     industry = %s, default_locale = %s, schema_version = %d, site_version = %d,
                     search_terms = %s, _minisite_current_version_id = %d, updated_at = NOW()
                 WHERE id = %s",
                array(
                    $this->wordPressManager->jsonEncode($version->getSiteJsonAsArray()),
                    $version->title,
                    $version->name,
                    $version->city,
                    $version->region,
                    $version->countryCode,
                    $version->postalCode,
                    $version->siteTemplate,
                    $version->palette,
                    $version->industry,
                    $version->defaultLocale,
                    $version->schemaVersion,
                    $version->siteVersion,
                    $version->searchTerms,
                    $versionId,
                    $minisiteId,
                )
            );

            // Update location_point if geo data exists
            if ($version->geo && $version->geo->getLat() && $version->geo->getLng()) {
                db::query(
                    "UPDATE {$wpdb->prefix}minisites SET location_point = POINT(%f, %f) WHERE id = %s",
                    array($version->geo->getLng(), $version->geo->getLat(), $minisiteId)
                );
            }

            db::query('COMMIT');
        } catch (\Exception $e) {
            db::query('ROLLBACK');

            throw $e;
        }
    }

    /**
     * Perform the actual rollback version creation
     */
    private function performCreateRollbackVersion(
        string $minisiteId,
        int $sourceVersionId,
        int $userId
    ): Version {
        $sourceVersion = $this->versionRepository->findById($sourceVersionId);
        $nextVersion = $this->versionRepository->getNextVersionNumber($minisiteId);

        $rollbackVersion = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: "Rollback to v{$sourceVersion->versionNumber}",
            comment: "Rollback from version {$sourceVersion->versionNumber}",
            createdBy: $userId,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: $sourceVersionId,
            siteJson: $sourceVersion->getSiteJsonAsArray(),
            // Copy all profile fields from source version to ensure preview works correctly
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

        return $this->versionRepository->save($rollbackVersion);
    }

    /**
     * Get minisite for rendering (with access check)
     */
    public function getMinisiteForRendering(string $siteId): ?object
    {
        $minisite = $this->minisiteRepository->findById($siteId);
        if (! $minisite) {
            return null;
        }

        // Verify user has access to this minisite
        if (! $this->hasUserAccess($minisite)) {
            return null;
        }

        return $minisite;
    }

    /**
     * Check if current user has access to the minisite
     */
    private function hasUserAccess(object $minisite): bool
    {
        if (! $this->wordPressManager->isUserLoggedIn()) {
            return false;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();

        return $minisite->createdBy === (int) $currentUser->ID;
    }
}
