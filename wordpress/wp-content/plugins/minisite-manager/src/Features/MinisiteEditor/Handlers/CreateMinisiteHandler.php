<?php

namespace Minisite\Features\MinisiteEditor\Handlers;

use Minisite\Features\MinisiteEditor\Commands\CreateMinisiteCommand;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;

/**
 * Create Minisite Handler
 *
 * Handles create minisite command execution by delegating to MinisiteEditorService.
 */
final class CreateMinisiteHandler
{
    public function __construct(
        private MinisiteEditorService $editorService
    ) {
    }

    /**
     * Handle create minisite command
     *
     * @param CreateMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function handle(CreateMinisiteCommand $command): array
    {
        return $this->editorService->createMinisite($command);
    }
}
