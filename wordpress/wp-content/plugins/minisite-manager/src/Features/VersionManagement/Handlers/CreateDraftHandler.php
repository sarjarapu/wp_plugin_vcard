<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Services\VersionService;

/**
 * Handler for creating draft versions
 */
class CreateDraftHandler
{
    public function __construct(
        private VersionService $versionService
    ) {
    }

    /**
     * Handle the create draft command
     */
    public function handle(CreateDraftCommand $command): \Minisite\Domain\Entities\Version
    {
        return $this->versionService->createDraft($command);
    }
}
