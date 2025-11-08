<?php

namespace Minisite\Features\MinisiteViewer\Services;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface;

/**
 * Minisite View Service
 *
 * SINGLE RESPONSIBILITY: Handle minisite view business logic
 * - Manages minisite data retrieval and validation
 * - Handles view logic and error conditions
 * - Provides clean interface for view operations
 */
class MinisiteViewService
{
    public function __construct(
        private WordPressMinisiteManager $wordPressManager,
        private MinisiteRepository $minisiteRepository,
        private VersionRepositoryInterface $versionRepository
    ) {
    }

    /**
     * Get minisite for view
     *
     * @param ViewMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function getMinisiteForView(ViewMinisiteCommand $command): array
    {
        try {
            $slugPair = new SlugPair($command->businessSlug, $command->locationSlug);
            $minisite = $this->minisiteRepository->findBySlugs($slugPair);

            if (! $minisite) {
                return array(
                    'success' => false,
                    'error' => 'Minisite not found',
                );
            }

            return array(
                'success' => true,
                'minisite' => $minisite,
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => 'Error retrieving minisite: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Check if minisite exists
     *
     * @param ViewMinisiteCommand $command
     * @return bool
     */
    public function minisiteExists(ViewMinisiteCommand $command): bool
    {
        $slugPair = new SlugPair($command->businessSlug, $command->locationSlug);
        return $this->minisiteRepository->findBySlugs($slugPair) !== null;
    }

    /**
     * Get minisite for version-specific preview (authenticated)
     *
     * @param string $siteId
     * @param string|null $versionId
     * @return object
     * @throws \RuntimeException
     */
    public function getMinisiteForVersionSpecificPreview(string $siteId, ?string $versionId): object
    {
        // Get minisite and verify access
        $minisite = $this->minisiteRepository->findById($siteId);
        if (! $minisite) {
            throw new \RuntimeException('Minisite not found');
        }

        // Check access permissions (authentication required for version-specific preview)
        $currentUser = $this->wordPressManager->getCurrentUser();
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            throw new \RuntimeException('Access denied');
        }

        // Handle version-specific preview
        $siteJson = null;
        $version = null;

        if ($versionId === 'current' || ! $versionId) {
            // Show current published version (from profile.siteJson)
            $siteJson = $minisite->siteJson;
        } else {
            // Show specific version
            $version = $this->versionRepository->findById((int) $versionId);
            if (! $version) {
                throw new \RuntimeException('Version not found');
            }
            if ($version->minisiteId !== $siteId) {
                throw new \RuntimeException('Version not found');
            }
            // Version stores siteJson as JSON string, decode it for Minisite (which expects array)
            $siteJson = json_decode($version->siteJson, true);
        }

        // Update profile with version-specific data for rendering
        $minisite->siteJson = $siteJson;

        // If showing a specific version, also update the profile fields from version data
        if ($version) {
            // Use version data if available, otherwise fall back to existing minisite data
            $minisite->name = $version->name ?? $minisite->name;
            $minisite->city = $version->city ?? $minisite->city;
            $minisite->title = $version->title ?? $minisite->title;
            // Add other fields as needed
        }

        return (object) array(
            'minisite' => $minisite,
            'version' => $version,
            'siteJson' => $siteJson,
            'versionId' => $versionId,
        );
    }
}
