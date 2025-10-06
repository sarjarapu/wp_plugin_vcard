<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteEditor\WordPress;

use Minisite\Features\MinisiteEditor\Commands\CreateMinisiteCommand;
use Minisite\Features\MinisiteEditor\Commands\EditMinisiteCommand;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;

/**
 * WordPress Minisite Manager
 *
 * Wraps WordPress and minisite-related functions for better testability and dependency injection.
 * This class provides a clean interface to minisite management functions,
 * allowing us to mock them easily in tests.
 */
final class WordPressMinisiteManager
{
    public function __construct(
        private MinisiteRepository $minisiteRepository,
        private VersionRepository $versionRepository
    ) {
    }

    /**
     * List minisites by owner
     *
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array of minisites
     */
    public function listMinisitesByOwner(int $userId, int $limit = 50, int $offset = 0): array
    {
        $minisites = $this->minisiteRepository->listByOwner($userId, $limit, $offset);
        
        return array_map(function ($minisite) {
            return [
                'id' => $minisite->id,
                'title' => $minisite->title ?: $minisite->name,
                'name' => $minisite->name,
                'slugs' => [
                    'business' => $minisite->slugs->business,
                    'location' => $minisite->slugs->location,
                ],
                'status' => $minisite->status,
                'created_at' => $minisite->createdAt,
                'updated_at' => $minisite->updatedAt,
                'url' => home_url('/b/' . rawurlencode($minisite->slugs->business) . '/' . rawurlencode($minisite->slugs->location)),
                'edit_url' => home_url('/account/sites/' . $minisite->id . '/edit'),
                'versions_url' => home_url('/account/sites/' . $minisite->id . '/versions'),
            ];
        }, $minisites);
    }

    /**
     * Check if slugs are available
     *
     * @param string $businessSlug Business slug
     * @param string $locationSlug Location slug
     * @return bool True if available, false otherwise
     */
    public function areSlugsAvailable(string $businessSlug, string $locationSlug): bool
    {
        $slugs = new SlugPair($businessSlug, $locationSlug);
        $existing = $this->minisiteRepository->findBySlugs($slugs);
        return $existing === null;
    }

    /**
     * Create a new minisite
     *
     * @param CreateMinisiteCommand $command
     * @return object Created minisite
     */
    public function createMinisite(CreateMinisiteCommand $command): object
    {
        $slugs = new SlugPair($command->businessSlug, $command->locationSlug);
        $geo = null;
        
        if ($command->latitude !== null && $command->longitude !== null) {
            $geo = new GeoPoint($command->latitude, $command->longitude);
        }

        $minisite = new Minisite(
            id: null,
            slugs: $slugs,
            title: $command->businessName,
            name: $command->businessName,
            city: $command->businessCity,
            region: $command->businessRegion,
            countryCode: $command->businessCountry,
            postalCode: $command->businessPostal,
            geo: $geo,
            siteTemplate: 'default',
            palette: 'default',
            industry: 'general',
            defaultLocale: 'en',
            schemaVersion: '1.0.0',
            siteVersion: '1.0.0',
            searchTerms: '',
            status: 'draft',
            createdBy: $command->userId,
            createdAt: null,
            updatedAt: null
        );

        return $this->minisiteRepository->save($minisite);
    }

    /**
     * Edit an existing minisite
     *
     * @param EditMinisiteCommand $command
     * @return object Updated minisite
     */
    public function editMinisite(EditMinisiteCommand $command): object
    {
        $minisite = $this->minisiteRepository->findById($command->siteId);
        
        if (!$minisite) {
            throw new \Exception('Minisite not found');
        }

        // Create a new version with the updated data
        $nextVersion = $this->versionRepository->getNextVersionNumber($command->siteId);
        
        $geo = null;
        if ($command->latitude !== null && $command->longitude !== null) {
            $geo = new GeoPoint($command->latitude, $command->longitude);
        }

        $version = new Version(
            id: null,
            siteId: $command->siteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: $command->versionLabel ?: "Version {$nextVersion}",
            comment: $command->versionComment,
            createdBy: $command->userId,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: null,
            siteJson: null, // Will be populated by repository
            slugs: $minisite->slugs,
            title: $command->seoTitle,
            name: $command->businessName,
            city: $command->businessCity,
            region: $command->businessRegion,
            countryCode: $command->businessCountry,
            postalCode: $command->businessPostal,
            geo: $geo,
            siteTemplate: $command->siteTemplate,
            palette: $command->brandPalette,
            industry: $command->brandIndustry,
            defaultLocale: $command->defaultLocale,
            schemaVersion: $minisite->schemaVersion,
            siteVersion: $minisite->siteVersion,
            searchTerms: $command->searchTerms
        );

        $savedVersion = $this->versionRepository->save($version);

        // For unpublished minisites, update the main table
        $hasBeenPublished = $this->versionRepository->findPublishedVersion($command->siteId) !== null;
        
        if (!$hasBeenPublished) {
            $this->minisiteRepository->updateBusinessInfo(
                $command->siteId,
                [
                    'name' => $command->businessName,
                    'city' => $command->businessCity,
                    'region' => $command->businessRegion,
                    'country_code' => $command->businessCountry,
                    'postal_code' => $command->businessPostal,
                    'site_template' => $command->siteTemplate,
                    'palette' => $command->brandPalette,
                    'industry' => $command->brandIndustry,
                    'default_locale' => $command->defaultLocale,
                    'search_terms' => $command->searchTerms,
                ],
                $command->userId
            );

            if ($command->latitude !== null && $command->longitude !== null) {
                $this->minisiteRepository->updateCoordinates(
                    $command->siteId,
                    $command->latitude,
                    $command->longitude,
                    $command->userId
                );
            }

            if (!empty($command->seoTitle)) {
                $this->minisiteRepository->updateTitle($command->siteId, $command->seoTitle);
            }
        }

        return $minisite;
    }

    /**
     * Check if user has access to minisite
     *
     * @param string $siteId Site ID
     * @param int $userId User ID
     * @return bool True if user has access, false otherwise
     */
    public function hasUserAccess(string $siteId, int $userId): bool
    {
        $minisite = $this->minisiteRepository->findById($siteId);
        return $minisite && $minisite->createdBy === $userId;
    }

    /**
     * Get minisite for preview
     *
     * @param string $siteId Site ID
     * @param string $versionId Version ID
     * @return object|null Minisite data for preview
     */
    public function getMinisiteForPreview(string $siteId, string $versionId): ?object
    {
        if ($versionId === 'current') {
            return $this->minisiteRepository->findById($siteId);
        }
        
        return $this->versionRepository->findById($versionId);
    }
}
