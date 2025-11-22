<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rendering {

    use Minisite\Application\Rendering\TimberRenderer;
    use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
    use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;

    #[CoversClass(TimberRenderer::class)]
    final class TimberRendererTest extends TestCase
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

        public function test_constructor_sets_variant(): void
        {
            $renderer = new TimberRenderer('v2024');

            $variantProperty = (new \ReflectionClass(TimberRenderer::class))->getProperty('variant');
            $variantProperty->setAccessible(true);

            $this->assertSame('v2024', $variantProperty->getValue($renderer));
        }

        public function test_constructor_uses_default_variant(): void
        {
            $renderer = new TimberRenderer();

            $variantProperty = (new \ReflectionClass(TimberRenderer::class))->getProperty('variant');
            $variantProperty->setAccessible(true);

            $this->assertSame('v2025', $variantProperty->getValue($renderer));
        }

        public function test_render_with_timber_available_calls_render_and_registers_locations(): void
        {
            $renderer = new TimberRenderer();
            $viewModel = $this->createViewModel();

            $output = $this->captureOutput(static function () use ($renderer, $viewModel): void {
                $renderer->render($viewModel);
            });

            $this->assertStringContainsString('<div class="auth-page">', $output);
            $this->assertNotEmpty(\Timber\Timber::$renderCalls);

            $renderCall = \Timber\Timber::$renderCalls[0];
            $this->assertSame(array('v2025/minisite.twig'), $renderCall['templates']);
            $this->assertSame($viewModel->toArray(), $renderCall['context']);

            $expectedPath = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
            $this->assertContains($expectedPath, \Timber\Timber::$locations);
        }

        public function test_render_uses_variant_in_template_path(): void
        {
            $renderer = new TimberRenderer('v2030');
            $viewModel = $this->createViewModel();

            $this->captureOutput(static function () use ($renderer, $viewModel): void {
                $renderer->render($viewModel);
            });

            $renderCall = \Timber\Timber::$renderCalls[0];
            $this->assertSame(array('v2030/minisite.twig'), $renderCall['templates']);
        }

        public function test_render_without_timber_calls_fallback_and_sets_header(): void
        {
            $renderer = new TimberRenderer();
            $viewModel = $this->createViewModel('No Timber Title', 'No Timber Name');

            $GLOBALS['_test_override_application_class_exists'] = static function (string $class): bool {
                if ($class === 'Timber\\Timber') {
                    return false;
                }

                return \class_exists($class);
            };

            $output = $this->captureOutput(static function () use ($renderer, $viewModel): void {
                $renderer->render($viewModel);
            });

            $this->assertStringContainsString('<!doctype html>', $output);
            $this->assertStringContainsString('No Timber Title', $output);
            $this->assertStringContainsString('No Timber Name', $output);
            $this->assertSame('Content-Type: text/html; charset=utf-8', $GLOBALS['_test_renderer_headers'][0]['header'] ?? null);
            $this->assertEmpty(\Timber\Timber::$renderCalls, 'Timber::render should not be called when Timber is unavailable');
        }

        public function test_render_fallback_outputs_expected_html_structure(): void
        {
            $renderer = new TimberRenderer();
            $viewModel = $this->createViewModel('Fallback Title', 'Fallback Name');

            $method = (new \ReflectionClass(TimberRenderer::class))->getMethod('renderFallback');
            $method->setAccessible(true);

            $output = $this->captureOutput(static function () use ($method, $renderer, $viewModel): void {
                $method->invoke($renderer, $viewModel);
            });

            $this->assertStringContainsString('<!doctype html>', $output);
            $this->assertStringContainsString('<meta charset="utf-8">', $output);
            $this->assertStringContainsString('<title>Fallback Title</title>', $output);
            $this->assertStringContainsString('<h1>Fallback Name</h1>', $output);
        }

        public function test_render_fallback_escapes_html_entities(): void
        {
            $renderer = new TimberRenderer();
            $viewModel = $this->createViewModel('<script>alert(1)</script>', '<b>unsafe</b>');

            $method = (new \ReflectionClass(TimberRenderer::class))->getMethod('renderFallback');
            $method->setAccessible(true);

            $output = $this->captureOutput(static function () use ($method, $renderer, $viewModel): void {
                $method->invoke($renderer, $viewModel);
            });

            $this->assertStringNotContainsString('<script>', $output);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
            $this->assertStringContainsString('&lt;b&gt;unsafe&lt;/b&gt;', $output);
        }

        public function test_render_fallback_handles_empty_minisite_data(): void
        {
            $renderer = new TimberRenderer();
            $minisite = $this->createViewModel('', '')->getMinisite();
            $minisite->title = '';
            $minisite->name = '';

            $method = (new \ReflectionClass(TimberRenderer::class))->getMethod('renderFallback');
            $method->setAccessible(true);

            $output = $this->captureOutput(static function () use ($method, $renderer, $minisite): void {
                $viewModel = new MinisiteViewModel($minisite);
                $method->invoke($renderer, $viewModel);
            });

            $this->assertStringContainsString('<title></title>', $output);
            $this->assertStringContainsString('<h1></h1>', $output);
        }

        public function test_register_timber_locations_preserves_existing_and_removes_duplicates(): void
        {
            \Timber\Timber::$locations = array(
                '/custom/location',
                trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber',
            );

            $renderer = new TimberRenderer();
            $method = (new \ReflectionClass(TimberRenderer::class))->getMethod('registerTimberLocations');
            $method->setAccessible(true);

            $method->invoke($renderer);

            $expected = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
            $this->assertContains('/custom/location', \Timber\Timber::$locations);
            $this->assertContains($expected, \Timber\Timber::$locations);

            $keys = array_keys(\Timber\Timber::$locations);
            $this->assertSame(range(0, count(\Timber\Timber::$locations) - 1), $keys, 'Locations array should be re-indexed');

            $method->invoke($renderer);
            $this->assertSame(\Timber\Timber::$locations[1], $expected, 'Plugin path should only appear once');
        }

        public function test_render_passes_view_model_context_to_timber(): void
        {
            $renderer = new TimberRenderer();
            $viewModel = $this->createViewModel('Context Title', 'Context Name');

            $this->captureOutput(static function () use ($renderer, $viewModel): void {
                $renderer->render($viewModel);
            });

            $renderCall = \Timber\Timber::$renderCalls[0];
            $context = $viewModel->toArray();

            $this->assertSame($context['minisite']->title, $renderCall['context']['minisite']->title);
            $this->assertSame($context['reviews'], $renderCall['context']['reviews']);
            $this->assertTrue($renderCall['context']['minisite']->canEdit);
            $this->assertTrue($renderCall['context']['minisite']->isBookmarked);
        }

        private function createViewModel(string $title = 'Test Title', string $name = 'Test Name'): MinisiteViewModel
        {
            $minisite = new Minisite(
                id: '1234567890abcdef12345678',
                slug: 'test-slug',
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

            return new MinisiteViewModel(
                minisite: $minisite,
                reviews: array(
                    array('rating' => 5, 'comment' => 'Excellent!'),
                ),
                isBookmarked: true,
                canEdit: true
            );
        }

        /**
         * Capture echo/print output for assertions.
         */
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
}
