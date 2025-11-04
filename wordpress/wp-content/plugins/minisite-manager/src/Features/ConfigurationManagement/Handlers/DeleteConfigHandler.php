<?php

namespace Minisite\Features\ConfigurationManagement\Handlers;

use Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;

/**
 * DeleteConfigHandler
 *
 * SINGLE RESPONSIBILITY: Handle delete configuration command
 */
final class DeleteConfigHandler
{
    public function __construct(
        private ConfigurationManagementService $configService
    ) {
    }

    public function handle(DeleteConfigCommand $command): void
    {
        $this->configService->delete($command->key);
    }
}
