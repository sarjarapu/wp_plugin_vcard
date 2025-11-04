<?php

namespace Minisite\Features\AppConfig\Handlers;

use Minisite\Features\AppConfig\Commands\DeleteConfigCommand;
use Minisite\Features\AppConfig\Services\AppConfigService;

/**
 * DeleteConfigHandler
 *
 * SINGLE RESPONSIBILITY: Handle delete configuration command
 */
final class DeleteConfigHandler
{
    public function __construct(
        private AppConfigService $configService
    ) {
    }

    public function handle(DeleteConfigCommand $command): void
    {
        $this->configService->delete($command->key);
    }
}
