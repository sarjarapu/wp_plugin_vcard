<?php

namespace Minisite\Features\Authentication\Rendering;

/**
 * Auth Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering with Timber
 * - Manages Timber template rendering
 * - Handles template context
 */
class AuthRenderer
{
    /**
     * Render authentication page using Timber
     */
    public function render(string $template, array $context = []): void
    {
        $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? [],
                    [$viewsBase]
                )
            )
        );

        \Timber\Timber::render($template, $context);
    }
}
