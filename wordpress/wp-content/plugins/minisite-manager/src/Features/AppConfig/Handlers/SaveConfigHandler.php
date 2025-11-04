<?php

namespace Minisite\Features\AppConfig\Handlers;

use Minisite\Features\AppConfig\Commands\SaveConfigCommand;
use Minisite\Features\AppConfig\Services\AppConfigService;

/**
 * SaveConfigHandler
 *
 * SINGLE RESPONSIBILITY: Handle save configuration command
 */
final class SaveConfigHandler
{
    public function __construct(
        private AppConfigService $configService
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
