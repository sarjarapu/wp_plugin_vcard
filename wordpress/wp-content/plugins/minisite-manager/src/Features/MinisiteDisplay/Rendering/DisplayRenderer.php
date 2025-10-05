<?php

namespace Minisite\Features\MinisiteDisplay\Rendering;

/**
 * Display Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering for minisite display
 * - Manages template rendering logic
 * - Handles fallback rendering
 * - Provides clean interface for display output
 */
final class DisplayRenderer
{
    private object $renderer;

    public function __construct(object $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Render minisite template
     *
     * @param object $minisite
     * @return void
     */
    public function renderMinisite(object $minisite): void
    {
        // Delegate to the Timber renderer if available
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($minisite);
            return;
        }

        // Fallback rendering if renderer is missing
        $this->renderFallback($minisite);
    }

    /**
     * Render template with context
     *
     * @param string $template
     * @param array $context
     * @return void
     */
    public function render(string $template, array $context): void
    {
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($template, $context);
            return;
        }

        // Fallback rendering
        $this->renderFallbackFromContext($context);
    }

    /**
     * Render 404 page
     *
     * @param string $errorMessage
     * @return void
     */
    public function render404(string $errorMessage = 'Minisite not found'): void
    {
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($errorMessage) . '</h1>';
    }

    /**
     * Fallback rendering for minisite
     *
     * @param object $minisite
     * @return void
     */
    private function renderFallback(object $minisite): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($minisite->name ?? 'Minisite') . '</h1>';
    }

    /**
     * Fallback rendering from context
     *
     * @param array $context
     * @return void
     */
    private function renderFallbackFromContext(array $context): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $title = $context['page_title'] ?? 'Page';
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($title) . '</h1>';
    }
}
