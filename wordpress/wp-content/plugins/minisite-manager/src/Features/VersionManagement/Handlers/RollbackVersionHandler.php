<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\Services\VersionService;

/**
 * Handler for creating rollback versions
 */
class RollbackVersionHandler
{
    public function __construct(
        private VersionService $versionService
    ) {
    }

    /**
     * Handle the rollback version command
     */
    public function handle(
        RollbackVersionCommand $command
    ): \Minisite\Features\VersionManagement\Domain\Entities\Version {
        return $this->versionService->createRollbackVersion($command);
    }
}
