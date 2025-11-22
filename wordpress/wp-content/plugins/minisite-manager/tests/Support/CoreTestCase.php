<?php

declare(strict_types=1);

namespace Tests\Support;

use Brain\Monkey;
use Minisite\Core\ActivationHandler;
use Minisite\Core\AdminMenuManager;
use Minisite\Core\DeactivationHandler;
use Minisite\Core\PluginBootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Core layer tests.
 *
 * Handles Brain Monkey lifecycle, resets WordPress globals,
 * and clears Core static test overrides between tests.
 */
abstract class CoreTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->defineTestConstants();
        $this->resetWordPressState();
    }

    protected function tearDown(): void
    {
        $this->resetWordPressState();
        ActivationHandler::resetTestState();
        DeactivationHandler::resetTestState();
        AdminMenuManager::resetTestState();
        PluginBootstrap::resetTestState();
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function resetWordPressState(): void
    {
        unset($GLOBALS['_test_options'], $GLOBALS['_test_roles'], $GLOBALS['_test_admin_menus']);
        unset($GLOBALS['wp_filter']);
    }

    private function defineTestConstants(): void
    {
        if (! defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
    }
}
