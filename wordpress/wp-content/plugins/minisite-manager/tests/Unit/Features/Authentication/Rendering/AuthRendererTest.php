<?php

namespace Tests\Unit\Features\Authentication\Rendering;

use Minisite\Features\Authentication\Rendering\AuthRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthRenderer
 * 
 * Tests the AuthRenderer for proper template rendering and fallback handling
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthRendererTest extends TestCase
{
    private AuthRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new AuthRenderer();
        
        // Mock Timber class for all tests
        $this->mockTimberClass();
        
        // Mock MINISITE_PLUGIN_DIR constant
        if (!defined('MINISITE_PLUGIN_DIR')) {
            define('MINISITE_PLUGIN_DIR', '/test/plugin/dir/');
        }
    }

    /**
     * Test render with Timber available
     */
    public function test_render_with_timber_available(): void
    {
        $template = 'test-template.twig';
        $context = ['page_title' => 'Test Page'];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should not output fallback content
        $this->assertStringNotContainsString('Authentication form not available', $output);
    }

    /**
     * Test render with Timber not available (fallback)
     */
    public function test_render_with_timber_not_available(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => 'Test Page',
            'error_msg' => 'Test error',
            'success_msg' => 'Test success'
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should output fallback content
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Test Page', $output);
        $this->assertStringContainsString('Test error', $output);
        $this->assertStringContainsString('Test success', $output);
        $this->assertStringContainsString('Authentication form not available', $output);
    }

    /**
     * Test render with empty context
     */
    public function test_render_with_empty_context(): void
    {
        $template = 'test-template.twig';
        $context = [];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should output fallback with default values
        $this->assertStringContainsString('Authentication', $output);
        $this->assertStringContainsString('Authentication form not available', $output);
    }

    /**
     * Test render with only page_title in context
     */
    public function test_render_with_only_page_title(): void
    {
        $template = 'test-template.twig';
        $context = ['page_title' => 'Custom Page Title'];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Custom Page Title', $output);
    }

    /**
     * Test render with only error_msg in context
     */
    public function test_render_with_only_error_msg(): void
    {
        $template = 'test-template.twig';
        $context = ['error_msg' => 'Custom Error Message'];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Custom Error Message', $output);
        $this->assertStringContainsString('color: red', $output);
    }

    /**
     * Test render with only success_msg in context
     */
    public function test_render_with_only_success_msg(): void
    {
        $template = 'test-template.twig';
        $context = ['success_msg' => 'Custom Success Message'];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Custom Success Message', $output);
        $this->assertStringContainsString('color: green', $output);
    }

    /**
     * Test render with all message types
     */
    public function test_render_with_all_message_types(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => 'Test Page',
            'error_msg' => 'Error message',
            'success_msg' => 'Success message',
            'message' => 'General message'
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Test Page', $output);
        $this->assertStringContainsString('Error message', $output);
        $this->assertStringContainsString('Success message', $output);
        $this->assertStringContainsString('General message', $output);
    }

    /**
     * Test render with empty strings in context
     */
    public function test_render_with_empty_strings_in_context(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => '',
            'error_msg' => '',
            'success_msg' => '',
            'message' => ''
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should not contain empty strings in output
        $this->assertStringNotContainsString('color: red', $output);
        $this->assertStringNotContainsString('color: green', $output);
    }

    /**
     * Test render with special characters in context
     */
    public function test_render_with_special_characters_in_context(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => 'Test & "Special" Characters',
            'error_msg' => 'Error with <script>alert("xss")</script>',
            'success_msg' => 'Success with &amp; entities'
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should contain escaped content
        $this->assertStringContainsString('Test &amp; &quot;Special&quot; Characters', $output);
        $this->assertStringContainsString('Error with &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
        $this->assertStringContainsString('Success with &amp; entities', $output);
    }

    /**
     * Test render with null values in context
     */
    public function test_render_with_null_values_in_context(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => null,
            'error_msg' => null,
            'success_msg' => null,
            'message' => null
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should not contain null values in output
        $this->assertStringNotContainsString('color: red', $output);
        $this->assertStringNotContainsString('color: green', $output);
    }

    /**
     * Test render with complex context data
     */
    public function test_render_with_complex_context_data(): void
    {
        $template = 'test-template.twig';
        $context = [
            'page_title' => 'Complex Test',
            'error_msg' => 'Error message',
            'success_msg' => 'Success message',
            'message' => 'General message',
            'additional_data' => 'This should not appear in fallback'
        ];
        
        // Capture output
        ob_start();
        $this->renderer->render($template, $context);
        $output = ob_get_clean();
        
        // Should contain expected content
        $this->assertStringContainsString('Complex Test', $output);
        $this->assertStringContainsString('Error message', $output);
        $this->assertStringContainsString('Success message', $output);
        $this->assertStringContainsString('General message', $output);
        
        // Should not contain additional data (fallback only handles specific fields)
        $this->assertStringNotContainsString('This should not appear in fallback', $output);
    }

    /**
     * Mock Timber class for testing
     * Note: Timber class is already mocked globally in bootstrap.php
     */
    private function mockTimberClass(): void
    {
        // Timber class is already mocked globally in bootstrap.php
        // No need to redeclare it here to avoid "Cannot redeclare class" errors
    }
}
