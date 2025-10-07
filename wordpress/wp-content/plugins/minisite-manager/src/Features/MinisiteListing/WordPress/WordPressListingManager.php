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
    private MinisiteRepository $minisiteRepository;
    private VersionRepository $versionRepository;

    public function __construct()
    {
        global $wpdb;
        $this->minisiteRepository = new MinisiteRepository($wpdb);
        $this->versionRepository = new VersionRepository($wpdb);
    }

    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        return wp_get_current_user();
    }

    /**
     * Check if current user has capability
     */
    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
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