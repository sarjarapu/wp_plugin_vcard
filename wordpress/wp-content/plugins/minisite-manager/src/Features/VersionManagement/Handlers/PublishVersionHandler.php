<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Services\VersionService;

/**
 * Handler for publishing versions
 */
class PublishVersionHandler
{
    public function __construct(
        private VersionService $versionService
    ) {
    }

    /**
     * Handle the publish version command
     */
    public function handle(PublishVersionCommand $command): void
    {
        $this->versionService->publishVersion($command);
    }
}
