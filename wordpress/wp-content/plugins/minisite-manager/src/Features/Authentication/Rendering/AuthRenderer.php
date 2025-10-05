<?php

namespace Minisite\Features\Authentication\Rendering;

/**
 * Auth Renderer
 * 
 * SINGLE RESPONSIBILITY: Handle template rendering
 * - Manages Timber template rendering
 * - Provides fallback rendering
 * - Handles template context
 */
final class AuthRenderer
{
    /**
     * Render authentication page using Timber
     */
    public function render(string $template, array $context = []): void
    {
        if (class_exists('Timber\\Timber')) {
            $this->renderWithTimber($template, $context);
            return;
        }

        $this->renderFallback($context);
    }

    /**
     * Render with Timber
     */
    private function renderWithTimber(string $template, array $context): void
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

    /**
     * Fallback rendering when Timber is not available
     */
    private function renderFallback(array $context): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<h1>' . esc_html($context['page_title'] ?? 'Authentication') . '</h1>';
        
        if (!empty($context['error_msg'])) {
            echo '<p style="color: red;">' . esc_html($context['error_msg']) . '</p>';
        }
        
        if (!empty($context['success_msg'])) {
            echo '<p style="color: green;">' . esc_html($context['success_msg']) . '</p>';
        }
        
        if (!empty($context['message'])) {
            echo '<p>' . esc_html($context['message']) . '</p>';
        }
        
        echo '<p>Authentication form not available (Timber required).</p>';
    }
}
