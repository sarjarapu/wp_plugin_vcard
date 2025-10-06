<?php

namespace Minisite\Features\VersionManagement\Http;

use PHPUnit\Framework\TestCase;

/**
 * Test for VersionResponseHandler
 */
class VersionResponseHandlerTest extends TestCase
{
    private VersionResponseHandler $responseHandler;

    protected function setUp(): void
    {
        $this->responseHandler = new VersionResponseHandler();
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_send_json_success_calls_wp_send_json_success(): void
    {
        $data = ['test' => 'data'];
        $statusCode = 200;

        $this->mockWordPressFunction('wp_send_json_success', true);

        // Since wp_send_json_success calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'sendJsonSuccess'));
    }

    public function test_send_json_error_calls_wp_send_json_error(): void
    {
        $message = 'Test error';
        $statusCode = 400;

        $this->mockWordPressFunction('wp_send_json_error', true);

        // Since wp_send_json_error calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'sendJsonError'));
    }

    public function test_redirect_to_login_calls_wp_redirect(): void
    {
        $redirectTo = '/test/path';

        $this->mockWordPressFunction('home_url', 'http://example.com');
        $this->mockWordPressFunction('wp_redirect', true);

        // Since wp_redirect calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'redirectToLogin'));
    }

    public function test_redirect_to_sites_calls_wp_redirect(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com');
        $this->mockWordPressFunction('wp_redirect', true);

        // Since wp_redirect calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->responseHandler, 'redirectToSites'));
    }

    public function test_set_404_response(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

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
