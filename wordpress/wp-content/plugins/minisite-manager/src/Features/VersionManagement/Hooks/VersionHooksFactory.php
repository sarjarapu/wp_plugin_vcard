<?php

namespace Minisite\Features\VersionManagement\Hooks;

use Minisite\Features\VersionManagement\Controllers\VersionController;
use Minisite\Features\VersionManagement\Handlers\CreateDraftHandler;
use Minisite\Features\VersionManagement\Handlers\ListVersionsHandler;
use Minisite\Features\VersionManagement\Handlers\PublishVersionHandler;
use Minisite\Features\VersionManagement\Handlers\RollbackVersionHandler;
use Minisite\Features\VersionManagement\Http\VersionRequestHandler;
use Minisite\Features\VersionManagement\Http\VersionResponseHandler;
use Minisite\Features\VersionManagement\Rendering\VersionRenderer;
use Minisite\Features\VersionManagement\Services\VersionService;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Application\Rendering\TimberRenderer;

/**
 * Factory for creating VersionHooks with all dependencies
 */
class VersionHooksFactory
{
    /**
     * Create VersionHooks instance with all dependencies
     */
    public static function create(): VersionHooks
    {
        // Create repositories
        global $wpdb;
        $minisiteRepository = new MinisiteRepository($wpdb);
        $versionRepository = new VersionRepository($wpdb);

        // Create service
        $versionService = new VersionService($minisiteRepository, $versionRepository);

        // Create handlers
        $listVersionsHandler = new ListVersionsHandler($versionService);
        $createDraftHandler = new CreateDraftHandler($versionService);
        $publishVersionHandler = new PublishVersionHandler($versionService);
        $rollbackVersionHandler = new RollbackVersionHandler($versionService);

        // Create HTTP components
        $requestHandler = new VersionRequestHandler();
        $responseHandler = new VersionResponseHandler();

        // Create renderer
        $timberRenderer = new TimberRenderer();
        $versionRenderer = new VersionRenderer($timberRenderer);

        // Create controller
        $versionController = new VersionController(
            $listVersionsHandler,
            $createDraftHandler,
            $publishVersionHandler,
            $rollbackVersionHandler,
            $requestHandler,
            $responseHandler,
            $versionRenderer
        );

        // Create hooks
        return new VersionHooks($versionController);
    }
}
