<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteListing\WordPress;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

/**
 * WordPress Listing Manager
 *
 * SINGLE RESPONSIBILITY: Provide WordPress-specific utilities for listing functionality
 * - Handles listing minisites by owner
 * - Provides WordPress-specific data formatting
 * - Acts as a bridge between the listing service and WordPress
 */
final class WordPressListingManager
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
        
        $result = array_map(function ($minisite) {
            $route = home_url('/b/' . rawurlencode($minisite->slugs->business) . '/' . rawurlencode($minisite->slugs->location));
            $statusChip = $minisite->status === 'published' ? 'Published' : 'Draft';
            
            return [
                'id' => $minisite->id,
                'title' => $minisite->title ?: $minisite->name,
                'name' => $minisite->name,
                'slugs' => [
                    'business' => $minisite->slugs->business,
                    'location' => $minisite->slugs->location,
                ],
                'route' => $route,
                'location' => trim(
                    $minisite->city . (isset($minisite->region) && $minisite->region ? ', ' . $minisite->region : '') .
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
            ];
        }, $minisites);
        
        return $result;
    }
}