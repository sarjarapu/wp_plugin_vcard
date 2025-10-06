<?php

namespace Minisite\Features\MinisiteEditor;

use Minisite\Features\MinisiteEditor\Hooks\EditorHooksFactory;

/**
 * MinisiteEditor Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the MinisiteEditor feature
 * - Initializes the minisite editing system
 * - Registers all editor hooks
 * - Provides a clean entry point for the feature
 */
final class MinisiteEditorFeature
{
    /**
     * Initialize the MinisiteEditor feature
     */
    public static function initialize(): void
    {
        $editorHooks = EditorHooksFactory::create();
        $editorHooks->register();
    }
}
