<?php

namespace Minisite\Application\Rendering;

use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;

/**
 * Timber Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering using Timber
 * - Registers Timber template locations
 * - Renders templates with provided view model data
 * - Provides fallback rendering when Timber is not available
 *
 * This class no longer handles data fetching - that is done by MinisiteViewDataService.
 */
class TimberRenderer
{
    public function __construct(private string $variant = 'v2025')
    {
    }

    /**
     * Render minisite using view model
     *
     * @param MinisiteViewModel $viewModel View model containing all data needed for rendering
     * @return void
     */
    public function render(MinisiteViewModel $viewModel): void
    {
        if (! class_exists('Timber\\Timber')) {
            $this->renderFallback($viewModel);

            return;
        }

        $this->registerTimberLocations();
        $context = $viewModel->toArray();

        \Timber\Timber::render(
            array(
                $this->variant . '/minisite.twig',
            ),
            $context
        );
    }

    /**
     * Render fallback when Timber is not available
     *
     * @param MinisiteViewModel $viewModel View model containing minisite data
     * @return void
     */
    protected function renderFallback(MinisiteViewModel $viewModel): void
    {
        $minisite = $viewModel->getMinisite();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<title>' . esc_html($minisite->title) . '</title>';
        echo '<h1>' . esc_html($minisite->name) . '</h1>';
    }

    /**
     * Register Timber template locations
     *
     * @return void
     */
    protected function registerTimberLocations(): void
    {
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? array(),
                    array( $base )
                )
            )
        );
    }
}
