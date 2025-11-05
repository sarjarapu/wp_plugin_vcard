<?php

namespace Minisite\Features\MinisiteListing\Services;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;

/**
 * Minisite Listing Service
 *
 * Handles minisite listing business logic.
 */
class MinisiteListingService
{
    public function __construct(
        private WordPressListingManager $listingManager
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
            $minisites = $this->listingManager->listMinisitesByOwner(
                $command->userId,
                $command->limit,
                $command->offset
            );

            return array(
                'success' => true,
                'minisites' => $minisites,
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => 'Failed to retrieve minisites: ' . $e->getMessage(),
            );
        }
    }
}
