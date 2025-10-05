<?php

namespace Minisite\Features\VersionManagement\Hooks;

use Minisite\Features\VersionManagement\Controllers\VersionController;
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
        $this->hooks = new VersionHooks($this->versionController);
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
        $functions = ['add_action', 'is_page'];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
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

    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    private function clearWordPressMocks(): void
    {
        $functions = ['add_action', 'is_page'];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
