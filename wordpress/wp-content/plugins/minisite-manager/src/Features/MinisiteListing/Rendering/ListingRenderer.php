<?php

namespace Minisite\Features\MinisiteListing\Rendering;

/**
 * Listing Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering with Timber for listing functionality
 * - Manages Timber template rendering
 * - Handles template context
 *
 * REQUIRES: Timber library must be installed via Composer
 */
class ListingRenderer
{
    /**
     * Render list page using Timber
     *
     * @param array $data Template data
     * @throws \RuntimeException If Timber is not available
     */
    public function renderListPage(array $data): void
    {
        if (! class_exists('Timber\\Timber')) {
            throw new \RuntimeException(
                'Timber library is required but not installed. ' .
                'Please install it via Composer: composer require timber/timber'
            );
        }

        $this->registerTimberLocations();
        \Timber\Timber::render('account-sites.twig', $data);
    }

    /**
     * Register Timber template locations
     */
    private function registerTimberLocations(): void
    {
        $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? array(),
                    array($viewsBase)
                )
            )
        );
    }
}
