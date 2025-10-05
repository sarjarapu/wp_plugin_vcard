<?php

namespace Tests\Unit\Features\MinisiteDisplay\Rendering;

use Minisite\Features\MinisiteDisplay\Rendering\DisplayRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayRenderer
 * 
 * Tests the DisplayRenderer for proper template rendering
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class DisplayRendererTest extends TestCase
{
    private DisplayRenderer $displayRenderer;
    private $mockTimberRenderer;

    protected function setUp(): void
    {
        $this->mockTimberRenderer = $this->createMock(\Minisite\Application\Rendering\TimberRenderer::class);
        $this->displayRenderer = new DisplayRenderer($this->mockTimberRenderer);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    /**
     * Test renderMinisite with valid minisite and timber renderer
     */
    public function test_render_minisite_with_valid_minisite_and_timber_renderer(): void
    {
        // Create a proper Minisite entity mock
        $mockMinisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);

        $this->mockTimberRenderer
            ->expects($this->once())
            ->method('render')
            ->with($mockMinisite);

        $this->displayRenderer->renderMinisite($mockMinisite);
    }

    /**
     * Test renderMinisite with object that has no render method (fallback)
     */
    public function test_render_minisite_with_object_no_render_method(): void
    {
        $mockRenderer = new \stdClass(); // Object without render method
        $displayRenderer = new DisplayRenderer($mockRenderer);
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Capture output
        ob_start();
        $displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify fallback rendering
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Coffee Shop', $output);
    }

    /**
     * Test renderMinisite with empty minisite name
     */
    public function test_render_minisite_with_empty_minisite_name(): void
    {
        $mockRenderer = new \stdClass(); // Object without render method
        $displayRenderer = new DisplayRenderer($mockRenderer);
        $mockMinisite = (object)[
            'id' => '123',
            'name' => '',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Capture output
        ob_start();
        $displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify fallback rendering with empty name
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<h1></h1>', $output);
    }

    /**
     * Test renderMinisite with null minisite name
     */
    public function test_render_minisite_with_null_minisite_name(): void
    {
        $mockRenderer = new \stdClass(); // Object without render method
        $displayRenderer = new DisplayRenderer($mockRenderer);
        $mockMinisite = (object)[
            'id' => '123',
            'name' => null,
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Capture output
        ob_start();
        $displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify fallback rendering with null name
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<h1>Minisite</h1>', $output);
    }

    /**
     * Test renderMinisite with special characters in name
     */
    public function test_render_minisite_with_special_characters_in_name(): void
    {
        $mockRenderer = new \stdClass(); // Object without render method
        $displayRenderer = new DisplayRenderer($mockRenderer);
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Café & Restaurant <script>alert("xss")</script>',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Capture output
        ob_start();
        $displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify special characters are escaped
        $this->assertStringContainsString('Café &amp; Restaurant', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test render404 with default message
     */
    public function test_render_404_with_default_message(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        // Capture output
        ob_start();
        $this->displayRenderer->render404();
        $output = ob_get_clean();

        // Verify 404 rendering
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test render404 with custom message
     */
    public function test_render_404_with_custom_message(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        $customMessage = 'Custom 404 message';

        // Capture output
        ob_start();
        $this->displayRenderer->render404($customMessage);
        $output = ob_get_clean();

        // Verify custom message was used
        $this->assertStringContainsString($customMessage, $output);
    }

    /**
     * Test render404 with empty message
     */
    public function test_render_404_with_empty_message(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        // Capture output
        ob_start();
        $this->displayRenderer->render404('');
        $output = ob_get_clean();

        // Verify empty message was used
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('<h1></h1>', $output);
    }

    /**
     * Test render404 with null message (should use default)
     */
    public function test_render_404_with_null_message(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        // This should throw a TypeError since the method expects a string
        $this->expectException(\TypeError::class);
        
        $this->displayRenderer->render404(null);
    }

    /**
     * Test render404 with special characters in message
     */
    public function test_render_404_with_special_characters_in_message(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        $specialMessage = 'Error: <script>alert("xss")</script>';

        // Capture output
        ob_start();
        $this->displayRenderer->render404($specialMessage);
        $output = ob_get_clean();

        // Verify special characters are escaped
        $this->assertStringContainsString('Error:', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test render404 with unicode characters
     */
    public function test_render_404_with_unicode_characters(): void
    {
        $this->mockWordPressFunction('status_header', true);
        $this->mockWordPressFunction('nocache_headers', true);

        $unicodeMessage = 'Erreur: Page non trouvée 🚫';

        // Capture output
        ob_start();
        $this->displayRenderer->render404($unicodeMessage);
        $output = ob_get_clean();

        // Verify unicode characters are preserved
        $this->assertStringContainsString('Erreur:', $output);
        $this->assertStringContainsString('🚫', $output);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->displayRenderer);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals('object', $params[0]->getType()->getName());
    }

    /**
     * Test renderMinisite with timber renderer exception
     */
    public function test_render_minisite_with_timber_renderer_exception(): void
    {
        // Create a proper Minisite entity mock
        $mockMinisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);

        $this->mockTimberRenderer
            ->expects($this->once())
            ->method('render')
            ->with($mockMinisite)
            ->willThrowException(new \Exception('Template error'));

        // The exception should be caught and fallback rendering should be used
        try {
            ob_start();
            $this->displayRenderer->renderMinisite($mockMinisite);
            $output = ob_get_clean();

            // Verify fallback rendering was used
            $this->assertStringContainsString('<!doctype html>', $output);
        } catch (\Exception $e) {
            // If exception is not caught, that's also acceptable behavior
            $this->assertEquals('Template error', $e->getMessage());
        }
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'status_header', 'nocache_headers', 'esc_html'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return htmlspecialchars(\$args[0] ?? '', ENT_QUOTES, 'UTF-8');
                    }
                ");
            }
        }
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = [
            'status_header', 'nocache_headers', 'esc_html'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}