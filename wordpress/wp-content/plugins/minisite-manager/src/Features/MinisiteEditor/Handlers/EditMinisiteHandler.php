<?php

namespace Minisite\Features\MinisiteEditor\Handlers;

use Minisite\Features\MinisiteEditor\Commands\EditMinisiteCommand;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;

/**
 * Edit Minisite Handler
 *
 * Handles edit minisite command execution by delegating to MinisiteEditorService.
 */
final class EditMinisiteHandler
{
    public function __construct(
        private MinisiteEditorService $editorService
    ) {
    }

    /**
     * Handle edit minisite command
     *
     * @param EditMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function handle(EditMinisiteCommand $command): array
    {
        return $this->editorService->editMinisite($command);
    }
}
