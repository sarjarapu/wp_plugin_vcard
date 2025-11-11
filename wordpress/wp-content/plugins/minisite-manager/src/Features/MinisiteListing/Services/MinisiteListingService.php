<?php

namespace Minisite\Features\MinisiteListing\Services;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;

/**
 * Minisite Listing Service
 *
 * Handles minisite listing business logic.
 */
class MinisiteListingService
{
    public function __construct(
        private WordPressListingManager $listingManager,
        private MinisiteRepository $minisiteRepository
    ) {
    }

    /**
     * List user's minisites
     *
     * @param ListMinisitesCommand $command
     * @return array{success: bool, minisites?: array, error?: string}
     */
    public function listMinisites(ListMinisitesCommand $command): array
    {
        try {
            $minisites = $this->minisiteRepository->listByOwner(
                $command->userId,
                $command->limit,
                $command->offset
            );

            // Format minisites for response
            $formattedMinisites = array_map(function ($minisite) {
                $route = $this->listingManager->getHomeUrl(
                    '/b/' . rawurlencode($minisite->slugs->business) . '/' . rawurlencode($minisite->slugs->location)
                );
                $statusChip = $minisite->status === 'published' ? 'Published' : 'Draft';

                return array(
                    'id' => $minisite->id,
                    'title' => $minisite->title ?: $minisite->name,
                    'name' => $minisite->name,
                    'slugs' => array(
                        'business' => $minisite->slugs->business,
                        'location' => $minisite->slugs->location,
                    ),
                    'route' => $route,
                    'location' => trim(
                        $minisite->city .
                        (isset($minisite->region) && $minisite->region ? ', ' . $minisite->region : '') .
                        ', ' . $minisite->countryCode,
                        ', '
                    ),
                    'status' => $minisite->status,
                    'status_chip' => $statusChip,
                    'updated_at' => $minisite->updatedAt ? $minisite->updatedAt->format('Y-m-d H:i') : null,
                    'published_at' => $minisite->publishedAt ? $minisite->publishedAt->format('Y-m-d H:i') : null,
                    // TODO: real subscription and online flags
                    'subscription' => 'Unknown',
                    'online' => 'Unknown',
                );
            }, $minisites);

            return array(
                'success' => true,
                'minisites' => $formattedMinisites,
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => 'Failed to retrieve minisites: ' . $e->getMessage(),
            );
        }
    }
}
