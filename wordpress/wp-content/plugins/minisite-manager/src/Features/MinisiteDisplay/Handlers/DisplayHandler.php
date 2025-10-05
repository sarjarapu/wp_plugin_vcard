<?php

namespace Minisite\Features\MinisiteDisplay\Handlers;

use Minisite\Features\MinisiteDisplay\Commands\DisplayMinisiteCommand;
use Minisite\Features\MinisiteDisplay\Services\MinisiteDisplayService;

/**
 * Display Handler
 *
 * SINGLE RESPONSIBILITY: Handle display command execution
 * - Delegates to MinisiteDisplayService
 * - Processes display requests
 * - Returns standardized results
 */
final class DisplayHandler
{
    public function __construct(
        private MinisiteDisplayService $displayService
    ) {
    }

    /**
     * Handle display command
     *
     * @param DisplayMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function handle(DisplayMinisiteCommand $command): array
    {
        return $this->displayService->getMinisiteForDisplay($command);
    }
}
