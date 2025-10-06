<?php

namespace Minisite\Features\MinisiteEditor\Handlers;

use Minisite\Features\MinisiteEditor\Commands\PreviewMinisiteCommand;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;

/**
 * Preview Minisite Handler
 *
 * Handles preview minisite command execution by delegating to MinisiteEditorService.
 */
final class PreviewMinisiteHandler
{
    public function __construct(
        private MinisiteEditorService $editorService
    ) {
    }

    /**
     * Handle preview minisite command
     *
     * @param PreviewMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function handle(PreviewMinisiteCommand $command): array
    {
        return $this->editorService->previewMinisite($command);
    }
}
