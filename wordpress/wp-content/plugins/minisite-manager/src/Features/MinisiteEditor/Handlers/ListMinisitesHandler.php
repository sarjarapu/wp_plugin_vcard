<?php

namespace Minisite\Features\MinisiteEditor\Handlers;

use Minisite\Features\MinisiteEditor\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;

/**
 * List Minisites Handler
 *
 * Handles list minisites command execution by delegating to MinisiteEditorService.
 */
final class ListMinisitesHandler
{
    public function __construct(
        private MinisiteEditorService $editorService
    ) {
    }

    /**
     * Handle list minisites command
     *
     * @param ListMinisitesCommand $command
     * @return array{success: bool, minisites?: array, error?: string}
     */
    public function handle(ListMinisitesCommand $command): array
    {
        return $this->editorService->listMinisites($command);
    }
}
