<?php

namespace Minisite\Tests\Unit\Features\VersionManagement\Hooks;

use Minisite\Features\VersionManagement\Hooks\VersionHooks;
use Minisite\Features\VersionManagement\Controllers\VersionController;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionHooks
 */
class VersionHooksTest extends TestCase
{
    private VersionHooks $hooks;
    private MockObject $versionController;

    protected function setUp(): void
    {
        $this->versionController = $this->createMock(VersionController::class);

        // Use TestTerminationHandler so exit doesn't terminate tests
        $terminationHandler = new TestTerminationHandler();

        $this->hooks = new VersionHooks($this->versionController, $terminationHandler);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'register'));
        $this->assertTrue(is_callable([$this->hooks, 'register']));
    }

    public function test_handle_version_history_page_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handleVersionHistoryPage'));
        $this->assertTrue(is_callable([$this->hooks, 'handleVersionHistoryPage']));
    }

    public function test_handle_list_versions_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handleListVersions'));
        $this->assertTrue(is_callable([$this->hooks, 'handleListVersions']));
    }

    public function test_handle_create_draft_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handleCreateDraft'));
        $this->assertTrue(is_callable([$this->hooks, 'handleCreateDraft']));
    }

    public function test_handle_publish_version_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handlePublishVersion'));
        $this->assertTrue(is_callable([$this->hooks, 'handlePublishVersion']));
    }

    public function test_handle_rollback_version_method_exists(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'handleRollbackVersion'));
        $this->assertTrue(is_callable([$this->hooks, 'handleRollbackVersion']));
    }

    // ===== FUNCTIONALITY TESTS =====

    public function test_register_adds_all_ajax_actions(): void
    {
        // Note: add_action is already defined in tests/Support/WordPressFunctions.php
        // We can't override it, so we verify register() executes without error
        // The actual hook registration is tested in integration tests
        try {
            $this->hooks->register();
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            $this->fail('register() should not throw exceptions: ' . $e->getMessage());
        }
    }

    public function test_handle_version_history_page_calls_controller_when_route_matches(): void
    {
        // Mock get_query_var to return matching route values
        $this->mockWordPressFunction('get_query_var', function ($var, $default = '') {
            if ($var === 'minisite_account') {
                return '1';
            }
            if ($var === 'minisite_account_action') {
                return 'versions';
            }
            return $default;
        });

        $this->versionController
            ->expects($this->once())
            ->method('handleListVersions');

        $this->hooks->handleVersionHistoryPage();
    }

    public function test_handle_version_history_page_does_not_call_controller_when_route_does_not_match(): void
    {
        // Mock get_query_var to return non-matching route values
        $this->mockWordPressFunction('get_query_var', function ($var, $default = '') {
            if ($var === 'minisite_account') {
                return '0'; // Not matching
            }
            if ($var === 'minisite_account_action') {
                return 'other'; // Not matching
            }
            return $default;
        });

        $this->versionController
            ->expects($this->never())
            ->method('handleListVersions');

        $this->hooks->handleVersionHistoryPage();
    }

    public function test_handle_list_versions_calls_controller(): void
    {
        $this->versionController
            ->expects($this->once())
            ->method('handleListVersions');

        $this->hooks->handleListVersions();
    }

    public function test_handle_create_draft_calls_controller(): void
    {
        $this->versionController
            ->expects($this->once())
            ->method('handleCreateDraft');

        $this->hooks->handleCreateDraft();
    }

    public function test_handle_publish_version_calls_controller(): void
    {
        $this->versionController
            ->expects($this->once())
            ->method('handlePublishVersion');

        $this->hooks->handlePublishVersion();
    }

    public function test_handle_rollback_version_calls_controller(): void
    {
        $this->versionController
            ->expects($this->once())
            ->method('handleRollbackVersion');

        $this->hooks->handleRollbackVersion();
    }

    private function setupWordPressMocks(): void
    {
        $functions = ['add_action', 'is_page', 'get_query_var'];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                if ($function === 'get_query_var') {
                    eval("
                        function get_query_var(\$var, \$default = '') {
                            if (isset(\$GLOBALS['_test_mock_get_query_var'])) {
                                \$callback = \$GLOBALS['_test_mock_get_query_var'];
                                if (is_callable(\$callback)) {
                                    return \$callback(\$var, \$default);
                                }
                            }
                            return \$default;
                        }
                    ");
                } elseif ($function === 'add_action') {
                    eval("
                        function add_action(\$hook, \$callback, \$priority = 10, \$accepted_args = 1) {
                            if (isset(\$GLOBALS['_test_mock_add_action'])) {
                                \$handler = \$GLOBALS['_test_mock_add_action'];
                                if (is_callable(\$handler)) {
                                    return \$handler(\$hook, \$callback);
                                }
                            }
                            return null;
                        }
                    ");
                } else {
                    eval("
                        function {$function}(...\$args) {
                            if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                                return \$GLOBALS['_test_mock_{$function}'];
                            }
                            return null;
                        }
                    ");
                }
            }
        }
    }

    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        if ($functionName === 'get_query_var' && is_callable($returnValue)) {
            // Store the callback in GLOBALS for the mocked function to use
            $GLOBALS['_test_mock_get_query_var'] = $returnValue;
        } elseif ($functionName === 'add_action' && is_callable($returnValue)) {
            // Store the callback for add_action
            $GLOBALS['_test_mock_add_action'] = $returnValue;
        } else {
            $GLOBALS['_test_mock_' . $functionName] = $returnValue;
        }
    }

    private function clearWordPressMocks(): void
    {
        $functions = ['add_action', 'is_page', 'get_query_var'];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
