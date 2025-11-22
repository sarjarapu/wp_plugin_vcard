<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Rendering;

use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimberRenderer::class)]
#[Group('integration')]
final class TimberRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Timber\Timber::$locations = array();
        \Timber\Timber::$renderCalls = array();
        unset($GLOBALS['_test_override_application_class_exists'], $GLOBALS['_test_renderer_headers']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_override_application_class_exists'], $GLOBALS['_test_renderer_headers']);
        \Timber\Timber::$locations = array();
        \Timber\Timber::$renderCalls = array();
        parent::tearDown();
    }

    public function test_render_with_timber_renders_template_output(): void
    {
        $renderer = new TimberRenderer();
        $viewModel = $this->createViewModel('Integration Title', 'Integration Name');

        $output = $this->captureOutput(static function () use ($renderer, $viewModel): void {
            $renderer->render($viewModel);
        });

        $this->assertStringContainsString('<div class="auth-page">', $output);
        $this->assertNotEmpty(\Timber\Timber::$renderCalls);
    }

    public function test_render_registers_plugin_template_location(): void
    {
        $renderer = new TimberRenderer('v2035');
        $viewModel = $this->createViewModel();

        $this->captureOutput(static function () use ($renderer, $viewModel): void {
            $renderer->render($viewModel);
        });

        $expectedPath = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
        $this->assertContains($expectedPath, \Timber\Timber::$locations);
    }

    public function test_render_without_timber_falls_back_to_html_output(): void
    {
        $renderer = new TimberRenderer();
        $viewModel = $this->createViewModel('Fallback Integration Title', 'Fallback Integration Name');

        $GLOBALS['_test_override_application_class_exists'] = static function (string $class): bool {
            if ($class === 'Timber\\Timber') {
                return false;
            }

            return \class_exists($class);
        };

        $output = $this->captureOutput(static function () use ($renderer, $viewModel): void {
            $renderer->render($viewModel);
        });

        $this->assertStringStartsWith('<!doctype html>', trim($output));
        $this->assertStringContainsString('Fallback Integration Title', $output);
        $this->assertStringContainsString('Fallback Integration Name', $output);
        $this->assertEmpty(\Timber\Timber::$renderCalls);
    }

    private function createViewModel(string $title = 'Test Title', string $name = 'Test Name'): MinisiteViewModel
    {
        $minisite = new Minisite(
            id: 'abcdefabcdefabcdefabcdef',
            slug: 'integration-slug',
            slugs: null,
            title: $title,
            name: $name,
            city: 'Austin',
            region: 'TX',
            countryCode: 'US',
            postalCode: '73301',
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array('blocks' => array()),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published'
        );

        return new MinisiteViewModel($minisite);
    }

    private function captureOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = ob_get_clean();
        }

        return $output;
    }
}
