<?php

namespace Minisite\Features\VersionManagement\Hooks;

use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\VersionManagement\Controllers\VersionController;
use Minisite\Features\VersionManagement\Handlers\CreateDraftHandler;
use Minisite\Features\VersionManagement\Handlers\ListVersionsHandler;
use Minisite\Features\VersionManagement\Handlers\PublishVersionHandler;
use Minisite\Features\VersionManagement\Handlers\RollbackVersionHandler;
use Minisite\Features\VersionManagement\Http\VersionRequestHandler;
use Minisite\Features\VersionManagement\Http\VersionResponseHandler;
use Minisite\Features\VersionManagement\Rendering\VersionRenderer;
use Minisite\Features\VersionManagement\Services\VersionService;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;

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
        // Require Doctrine-based repositories from global (initialized by PluginBootstrap)
        if (! isset($GLOBALS['minisite_repository'])) {
            throw new \RuntimeException(
                'MinisiteRepository not initialized. Ensure PluginBootstrap::initializeConfigSystem() is called.'
            );
        }
        if (! isset($GLOBALS['minisite_version_repository'])) {
            throw new \RuntimeException(
                'VersionRepository not initialized. Ensure PluginBootstrap::initializeConfigSystem() is called.'
            );
        }
        $minisiteRepository = $GLOBALS['minisite_repository'];
        $versionRepository = $GLOBALS['minisite_version_repository'];

        // Create termination handler for WordPress manager
        $terminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create WordPress manager (requires TerminationHandlerInterface)
        $wordPressManager = new WordPressVersionManager($terminationHandler);

        // Create service
        $versionService = new VersionService($minisiteRepository, $versionRepository, $wordPressManager);

        // Create handlers
        $listVersionsHandler = new ListVersionsHandler($versionService);
        $createDraftHandler = new CreateDraftHandler($versionService);
        $publishVersionHandler = new PublishVersionHandler($versionService);
        $rollbackVersionHandler = new RollbackVersionHandler($versionService);

        // Create HTTP components
        $formSecurityHelper = new FormSecurityHelper($wordPressManager);
        $requestHandler = new VersionRequestHandler($wordPressManager, $formSecurityHelper);
        $responseHandler = new VersionResponseHandler($wordPressManager);

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
            $versionRenderer,
            $versionService,
            $wordPressManager
        );

        // Create termination handler for hook (separate instance for hook)
        $hookTerminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create hooks
        return new VersionHooks($versionController, $hookTerminationHandler);
    }
}
