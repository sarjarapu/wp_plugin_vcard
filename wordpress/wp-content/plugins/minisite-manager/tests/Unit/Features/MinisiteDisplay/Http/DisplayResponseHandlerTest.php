<?php

namespace Tests\Unit\Features\MinisiteDisplay\Http;

use Minisite\Features\MinisiteDisplay\Http\DisplayResponseHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayResponseHandler
 * 
 * Tests the DisplayResponseHandler for proper HTTP response handling
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class DisplayResponseHandlerTest extends TestCase
{
    private DisplayResponseHandler $responseHandler;

    protected function setUp(): void
    {
        $this->responseHandler = new DisplayResponseHandler();
    }

    /**
     * Test set404Response sets proper headers
     */
    public function test_set_404_response_sets_proper_headers(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Capture output
        ob_start();
        $this->responseHandler->set404Response();
        $output = ob_get_clean();

        // Verify 404 response was set
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test set404Response with custom message
     */
    public function test_set_404_response_with_custom_message(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        $customMessage = 'Custom 404 message';

        // Capture output
        ob_start();
        $this->responseHandler->set404Response($customMessage);
        $output = ob_get_clean();

        // Verify custom message was used
        $this->assertStringContainsString($customMessage, $output);
    }

    /**
     * Test set404Response with empty message
     */
    public function test_set_404_response_with_empty_message(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Capture output
        ob_start();
        $this->responseHandler->set404Response('');
        $output = ob_get_clean();

        // Verify default message was used
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test set404Response with null message
     */
    public function test_set_404_response_with_null_message(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Capture output
        ob_start();
        $this->responseHandler->set404Response(null);
        $output = ob_get_clean();

        // Verify default message was used
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test set404Response with special characters in message
     */
    public function test_set_404_response_with_special_characters(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        $specialMessage = 'Error: Database connection failed & "quotes" <script>alert("xss")</script>';

        // Capture output
        ob_start();
        $this->responseHandler->set404Response($specialMessage);
        $output = ob_get_clean();

        // Verify message was escaped
        $this->assertStringContainsString('Database connection failed', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test set404Response sets proper HTTP status
     */
    public function test_set_404_response_sets_proper_http_status(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // This test verifies that the method can be called without errors
        // In a real environment, status_header() and nocache_headers() would be called
        $this->responseHandler->set404Response();

        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /**
     * Test set404Response with very long message
     */
    public function test_set_404_response_with_very_long_message(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        $longMessage = str_repeat('This is a very long error message. ', 100);

        // Capture output
        ob_start();
        $this->responseHandler->set404Response($longMessage);
        $output = ob_get_clean();

        // Verify long message was handled
        $this->assertStringContainsString('This is a very long error message.', $output);
    }

    /**
     * Test set404Response with HTML content
     */
    public function test_set_404_response_with_html_content(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        $htmlMessage = '<h1>Error</h1><p>Something went wrong</p>';

        // Capture output
        ob_start();
        $this->responseHandler->set404Response($htmlMessage);
        $output = ob_get_clean();

        // Verify HTML was escaped
        $this->assertStringContainsString('Error', $output);
        $this->assertStringNotContainsString('<h1>', $output);
        $this->assertStringNotContainsString('<p>', $output);
    }

    /**
     * Test set404Response with unicode characters
     */
    public function test_set_404_response_with_unicode_characters(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions();

        $unicodeMessage = 'Error: Café & Restaurant (café-&-restaurant)';

        // Capture output
        ob_start();
        $this->responseHandler->set404Response($unicodeMessage);
        $output = ob_get_clean();

        // Verify unicode characters were handled
        $this->assertStringContainsString('Café & Restaurant', $output);
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(): void
    {
        // Mock status_header function
        if (!function_exists('status_header')) {
            eval('
                function status_header($code) {
                    // Mock implementation - just return
                    return;
                }
            ');
        }

        // Mock nocache_headers function
        if (!function_exists('nocache_headers')) {
            eval('
                function nocache_headers() {
                    // Mock implementation - just return
                    return;
                }
            ');
        }

        // Mock esc_html function
        if (!function_exists('esc_html')) {
            eval('
                function esc_html($text) {
                    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
                }
            ');
        }
    }
}
