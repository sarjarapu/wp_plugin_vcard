<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionResponseHandler
 */
class VersionResponseHandlerTest extends TestCase
{
    private VersionResponseHandler $responseHandler;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = $this->createMock(WordPressVersionManager::class);
        $this->responseHandler = new VersionResponseHandler($this->wordPressManager);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_send_json_success_method_exists_and_callable(): void
    {
        // Since sendJsonSuccess calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'sendJsonSuccess'));
        $this->assertTrue(is_callable([$this->responseHandler, 'sendJsonSuccess']));
    }

    public function test_send_json_error_method_exists_and_callable(): void
    {
        // Since sendJsonError calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'sendJsonError'));
        $this->assertTrue(is_callable([$this->responseHandler, 'sendJsonError']));
    }

    public function test_redirect_to_login_method_exists_and_callable(): void
    {
        // Since redirect calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'redirectToLogin'));
        $this->assertTrue(is_callable([$this->responseHandler, 'redirectToLogin']));
    }

    public function test_redirect_to_sites_method_exists_and_callable(): void
    {
        // Since redirect calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'redirectToSites'));
        $this->assertTrue(is_callable([$this->responseHandler, 'redirectToSites']));
    }

    public function test_set_404_response(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('setStatusHeader')
            ->with(404);
        
        $this->wordPressManager
            ->expects($this->once())
            ->method('setNoCacheHeaders');

        // Mock global wp_query with a simple object that has set_404 method
        $mockWpQuery = new class {
            public function set_404() {
                return true;
            }
        };
        $GLOBALS['wp_query'] = $mockWpQuery;

        // Should not throw any exceptions
        $this->responseHandler->set404Response();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    private function setupWordPressMocks(): void
    {
        $functions = [
            'wp_send_json_success', 'wp_send_json_error', 'home_url', 'wp_redirect',
            'status_header', 'nocache_headers'
        ];

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
        $functions = [
            'wp_send_json_success', 'wp_send_json_error', 'home_url', 'wp_redirect',
            'status_header', 'nocache_headers'
        ];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
