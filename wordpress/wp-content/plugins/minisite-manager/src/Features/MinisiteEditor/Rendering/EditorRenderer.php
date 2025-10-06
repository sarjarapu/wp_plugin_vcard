<?php

namespace Minisite\Features\MinisiteEditor\Rendering;

/**
 * Editor Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering with Timber
 * - Manages Timber template rendering
 * - Handles template context
 */
final class EditorRenderer
{
    /**
     * Render list page using Timber
     */
    public function renderListPage(array $data): void
    {
        $this->render('account-sites.twig', $data);
    }

    /**
     * Render edit page using Timber
     */
    public function renderEditPage(array $data): void
    {
        $this->render('account-sites-edit.twig', $data);
    }

    /**
     * Render new page using Timber
     */
    public function renderNewPage(array $data): void
    {
        $this->render('account-sites-new.twig', $data);
    }

    /**
     * Render preview page using Timber
     */
    public function renderPreviewPage(array $data): void
    {
        $this->render('account-sites-preview.twig', $data);
    }

    /**
     * Render template using Timber
     */
    private function render(string $template, array $context = []): void
    {
        if (class_exists('Timber\\Timber')) {
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
            return;
        }

        // Fallback rendering
        $this->renderFallback($template, $context);
    }

    /**
     * Fallback rendering when Timber is not available
     */
    private function renderFallback(string $template, array $context): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        
        switch ($template) {
            case 'account-sites.twig':
                echo '<h1>My Minisites</h1>';
                if (!empty($context['minisites'])) {
                    echo '<ul>';
                    foreach ($context['minisites'] as $minisite) {
                        echo '<li><a href="' . esc_url($minisite['edit_url']) . '">' . esc_html($minisite['title']) . '</a></li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No minisites found.</p>';
                }
                break;
                
            case 'account-sites-edit.twig':
                echo '<h1>Edit Minisite</h1>';
                echo '<p>Edit form not available (Timber required).</p>';
                break;
                
            case 'account-sites-new.twig':
                echo '<h1>Create New Minisite</h1>';
                echo '<p>Create form not available (Timber required).</p>';
                break;
                
            case 'account-sites-preview.twig':
                echo '<h1>Preview Minisite</h1>';
                echo '<p>Preview not available (Timber required).</p>';
                break;
                
            default:
                echo '<h1>Page Not Available</h1>';
                echo '<p>Template rendering not available (Timber required).</p>';
        }
    }
}
