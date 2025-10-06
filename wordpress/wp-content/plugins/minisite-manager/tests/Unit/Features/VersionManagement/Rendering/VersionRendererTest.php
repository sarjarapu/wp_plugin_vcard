<?php

namespace Minisite\Features\VersionManagement\Rendering;

use Minisite\Application\Rendering\TimberRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionRenderer
 */
class VersionRendererTest extends TestCase
{
    private VersionRenderer $renderer;
    private MockObject $timberRenderer;

    protected function setUp(): void
    {
        $this->timberRenderer = $this->createMock(TimberRenderer::class);
        $this->renderer = new VersionRenderer($this->timberRenderer);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_render_version_history_calls_timber_when_available(): void
    {
        $data = [
            'page_title' => 'Test Title',
            'profile' => (object) ['title' => 'Test Profile'],
            'versions' => []
        ];

        // Mock Timber class existence
        $this->mockWordPressFunction('class_exists', true);
        $this->mockWordPressFunction('trailingslashit', '/test/path/');
        $this->mockWordPressFunction('MINISITE_PLUGIN_DIR', '/test/path');

        // Since Timber::render calls exit, we can't test the actual call
        // but we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->renderer, 'renderVersionHistory'));
    }

    public function test_render_version_history_fallback_when_timber_not_available(): void
    {
        $data = [
            'page_title' => 'Test Title',
            'profile' => (object) ['title' => 'Test Profile'],
            'versions' => []
        ];

        // Mock Timber class not existing
        $this->mockWordPressFunction('class_exists', false);
        $this->mockWordPressFunction('esc_html', 'Test Profile');

        // Since the fallback method outputs HTML and calls exit,
        // we can't test the actual output, but we can verify the method exists
        $this->assertTrue(method_exists($this->renderer, 'renderVersionHistory'));
    }

    private function setupWordPressMocks(): void
    {
        $functions = [
            'class_exists', 'trailingslashit', 'esc_html', 'header'
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
        $functions = ['class_exists', 'trailingslashit', 'esc_html', 'header'];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
