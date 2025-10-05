<?php

namespace Tests\Unit\Features\MinisiteDisplay\Rendering;

use Minisite\Features\MinisiteDisplay\Rendering\DisplayRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

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
    private $timberRenderer;

    protected function setUp(): void
    {
        $this->timberRenderer = $this->createMock(\Minisite\Application\Rendering\TimberRenderer::class);
        $this->displayRenderer = new DisplayRenderer($this->timberRenderer);
    }

    /**
     * Test renderMinisite with valid minisite and Timber renderer
     */
    public function test_render_minisite_with_valid_minisite_and_timber_renderer(): void
    {
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Mock Timber renderer
        $this->timberRenderer
            ->expects($this->once())
            ->method('render')
            ->with($mockMinisite);

        $this->displayRenderer->renderMinisite($mockMinisite);
    }

    /**
     * Test renderMinisite with null Timber renderer (fallback)
     */
    public function test_render_minisite_with_null_timber_renderer(): void
    {
        $displayRenderer = new DisplayRenderer(null);
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
        $displayRenderer = new DisplayRenderer(null);
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
        $displayRenderer = new DisplayRenderer(null);
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
        $this->assertStringContainsString('<h1></h1>', $output);
    }

    /**
     * Test renderMinisite with special characters in name
     */
    public function test_render_minisite_with_special_characters_in_name(): void
    {
        $displayRenderer = new DisplayRenderer(null);
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Café & Restaurant <script>alert("xss")</script>',
            'business_slug' => 'café-&-restaurant',
            'location_slug' => 'main-street-123'
        ];

        // Capture output
        ob_start();
        $displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify special characters are escaped
        $this->assertStringContainsString('Café & Restaurant', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test render404 with default message
     */
    public function test_render_404_with_default_message(): void
    {
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
        // Capture output
        ob_start();
        $this->displayRenderer->render404('');
        $output = ob_get_clean();

        // Verify default message was used
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test render404 with null message
     */
    public function test_render_404_with_null_message(): void
    {
        // Capture output
        ob_start();
        $this->displayRenderer->render404(null);
        $output = ob_get_clean();

        // Verify default message was used
        $this->assertStringContainsString('Minisite not found', $output);
    }

    /**
     * Test render404 with special characters in message
     */
    public function test_render_404_with_special_characters_in_message(): void
    {
        $specialMessage = 'Error: Database connection failed & "quotes" <script>alert("xss")</script>';

        // Capture output
        ob_start();
        $this->displayRenderer->render404($specialMessage);
        $output = ob_get_clean();

        // Verify special characters are escaped
        $this->assertStringContainsString('Database connection failed', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test render404 with unicode characters
     */
    public function test_render_404_with_unicode_characters(): void
    {
        $unicodeMessage = 'Error: Café & Restaurant (café-&-restaurant)';

        // Capture output
        ob_start();
        $this->displayRenderer->render404($unicodeMessage);
        $output = ob_get_clean();

        // Verify unicode characters were handled
        $this->assertStringContainsString('Café & Restaurant', $output);
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
        $this->assertEquals('?', $params[0]->getType()->getName()); // Nullable type
    }

    /**
     * Test renderMinisite with Timber renderer exception
     */
    public function test_render_minisite_with_timber_renderer_exception(): void
    {
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        // Mock Timber renderer to throw exception
        $this->timberRenderer
            ->expects($this->once())
            ->method('render')
            ->with($mockMinisite)
            ->willThrowException(new \Exception('Template not found'));

        // Capture output
        ob_start();
        $this->displayRenderer->renderMinisite($mockMinisite);
        $output = ob_get_clean();

        // Verify fallback rendering was used
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Coffee Shop', $output);
    }
}
