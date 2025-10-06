<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Services\VersionService;

/**
 * Handler for listing versions
 */
class ListVersionsHandler
{
    public function __construct(
        private VersionService $versionService
    ) {
    }

    /**
     * Handle the list versions command
     */
    public function handle(ListVersionsCommand $command): array
    {
        return $this->versionService->listVersions($command);
    }
}
