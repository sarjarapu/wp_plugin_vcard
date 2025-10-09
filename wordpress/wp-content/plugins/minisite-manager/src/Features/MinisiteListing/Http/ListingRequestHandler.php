<?php

namespace Minisite\Features\MinisiteListing\Http;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;

/**
 * Listing Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for listing functionality
 * - Validates HTTP method
 * - Extracts and sanitizes request data
 * - Creates command objects
 * - Handles nonce verification
 */
final class ListingRequestHandler
{
    public function __construct(
        private WordPressListingManager $wordPressManager
    ) {
    }

    /**
     * Parse list minisites request
     *
     * @return ListMinisitesCommand|null
     */
    public function parseListMinisitesRequest(): ?ListMinisitesCommand
    {
        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$currentUser || !$currentUser->ID || $currentUser->ID <= 0) {
            return null;
        }

        // Get pagination parameters
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

        // Validate limits
        $limit = max(1, min(100, $limit)); // Between 1 and 100
        $offset = max(0, $offset); // Non-negative

        return new ListMinisitesCommand(
            userId: $currentUser->ID,
            limit: $limit,
            offset: $offset
        );
    }
}
