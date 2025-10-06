<?php

namespace Minisite\Features\MinisiteListing\Handlers;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;

/**
 * List Minisites Handler
 *
 * Handles list minisites command execution by delegating to MinisiteListingService.
 */
final class ListMinisitesHandler
{
    public function __construct(
        private MinisiteListingService $listingService
    ) {
    }

    /**
     * Handle list minisites command
     *
     * @param ListMinisitesCommand $command
     * @return array{success: bool, minisites?: array, error?: string}
     */
    public function handle(ListMinisitesCommand $command): array
    {
        return $this->listingService->listMinisites($command);
    }
}
