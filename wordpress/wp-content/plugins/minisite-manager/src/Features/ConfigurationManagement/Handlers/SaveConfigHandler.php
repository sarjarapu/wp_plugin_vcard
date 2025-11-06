<?php

namespace Minisite\Features\ConfigurationManagement\Handlers;

use Minisite\Features\ConfigurationManagement\Commands\SaveConfigCommand;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;

/**
 * SaveConfigHandler
 *
 * SINGLE RESPONSIBILITY: Handle save configuration command
 */
class SaveConfigHandler
{
    public function __construct(
        private ConfigurationManagementService $configService
    ) {
    }

    public function handle(SaveConfigCommand $command): void
    {
        $this->configService->set(
            $command->key,
            $command->value,
            $command->type,
            $command->description
        );
    }
}
