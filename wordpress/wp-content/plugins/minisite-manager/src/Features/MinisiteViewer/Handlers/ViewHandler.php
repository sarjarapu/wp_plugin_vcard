<?php

namespace Minisite\Features\MinisiteViewer\Handlers;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;

/**
 * View Handler
 *
 * SINGLE RESPONSIBILITY: Handle view command execution
 * - Delegates to MinisiteViewService
 * - Processes view requests
 * - Returns standardized results
 */
class ViewHandler
{
    public function __construct(
        private MinisiteViewService $viewService
    ) {
    }

    /**
     * Handle view command
     *
     * @param ViewMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function handle(ViewMinisiteCommand $command): array
    {
        return $this->viewService->getMinisiteForView($command);
    }
}
