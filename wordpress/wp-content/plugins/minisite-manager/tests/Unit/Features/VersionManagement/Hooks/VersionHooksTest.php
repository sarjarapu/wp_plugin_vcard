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
