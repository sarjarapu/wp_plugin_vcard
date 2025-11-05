<?php

declare(strict_types=1);

namespace Minisite\Features\MinisiteListing\WordPress;

use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
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
class WordPressListingManager extends BaseWordPressManager
{
    private MinisiteRepository $minisiteRepository;
    private VersionRepository $versionRepository;

    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
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
     * Get home URL
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }

    /**
     * Redirect to URL
     * Uses base class redirect() method which handles termination
     */
    public function redirect(string $location, int $status = 302): void
    {
        parent::redirect($location, $status);
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
            $route = $this->getHomeUrl(
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
            );
        }, $minisites);

        return $result;
    }
}
