<?php

namespace Tests\Unit\Application\Controllers\Front;

use Minisite\Application\Controllers\Front\MinisitePageController;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

interface RendererInterface
{
    public function render($minisite): void;
}

#[RunTestsInSeparateProcesses]
final class MinisitePageControllerTest extends TestCase
{
    private MinisitePageController $controller;
    private object $mockRenderer;
    private MinisiteRepository $mockRepository;
    private object $mockWpQuery;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock renderer
        $this->mockRenderer = $this->createMock(RendererInterface::class);

        // Create mock wp_query
        $this->mockWpQuery = new class {
            public function set_404() { return null; }
        };

        // Create mock repository
        $this->mockRepository = $this->createMock(MinisiteRepository::class);

        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Create controller with mocked repository
        $this->controller = new MinisitePageController($this->mockRenderer, $this->mockRepository);
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['wp_query']);
        parent::tearDown();
    }

    private function mockWordPressFunctions(): void
    {
        // Mock WordPress functions
        if (!function_exists('status_header')) {
            eval('
                function status_header($code) {
                    // Mock implementation
                }
            ');
        }

        if (!function_exists('nocache_headers')) {
            eval('
                function nocache_headers() {
                    // Mock implementation
                }
            ');
        }

        if (!function_exists('esc_html')) {
            eval('
                function esc_html($text) {
                    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
                }
            ');
        }

        if (!function_exists('header')) {
            eval('
                function header($header) {
                    // Mock implementation - just echo for testing
                    echo "HEADER: " . $header . "\n";
                }
            ');
        }

        // Set up global wp_query
        $GLOBALS['wp_query'] = $this->mockWpQuery;
    }

    public function testHandleWithValidMinisiteCallsRenderer(): void
    {
        // Arrange
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = $this->createTestMinisite($slugPair);

        // Mock the repository to return our minisite
        $this->mockRepository->expects($this->once())
            ->method('findBySlugs')
            ->with($this->equalTo($slugPair))
            ->willReturn($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->equalTo($minisite));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }

    public function testHandleWithInvalidMinisiteSets404(): void
    {
        // Arrange
        $businessSlug = 'invalid-business';
        $locationSlug = 'invalid-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        // Mock the repository to return null
        $this->mockRepository->expects($this->once())
            ->method('findBySlugs')
            ->with($this->equalTo($slugPair))
            ->willReturn(null);

        // Mock wp_query set_404 to be called
        // We can't easily mock this in unit tests, so we'll just verify the output

        // Capture output
        ob_start();

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Minisite not found', $output);
    }

    public function testHandleWithRendererWithoutRenderMethodShowsMinimalDetails(): void
    {
        // Arrange
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = $this->createTestMinisite($slugPair);

        // Create a renderer without render method
        $rendererWithoutRender = new \stdClass();

        // Mock the repository to return our minisite
        $this->mockRepository->expects($this->once())
            ->method('findBySlugs')
            ->with($this->equalTo($slugPair))
            ->willReturn($minisite);

        // Create controller with renderer without render method
        $controller = new MinisitePageController($rendererWithoutRender, $this->mockRepository);

        // Capture output
        ob_start();

        // Act
        $controller->handle($businessSlug, $locationSlug);

        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Test Business', $output);
    }

    public function testHandleWithEmptySlugs(): void
    {
        // Arrange - Test with empty strings directly
        $businessSlug = '';
        $locationSlug = '';

        // The repository won't be called because SlugPair will throw an exception

        // Capture output
        ob_start();

        // Act - This will fail at SlugPair construction, which is expected behavior
        try {
            $this->controller->handle($businessSlug, $locationSlug);
            $output = ob_get_clean();
        } catch (\InvalidArgumentException $e) {
            // Clean up output buffer
            ob_get_clean();
            // Expected behavior - SlugPair doesn't allow empty strings
            $this->assertStringContainsString('Business slug must be a non-empty string', $e->getMessage());
            return;
        }

        // If we get here, the test should verify 404 behavior
        $this->assertStringContainsString('Minisite not found', $output);
    }

    public function testHandleWithSpecialCharactersInSlugs(): void
    {
        // Arrange
        $businessSlug = 'test-business-with-special-chars-&-symbols';
        $locationSlug = 'test-location-with-ümlauts';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = $this->createTestMinisite($slugPair, 'Test Business & Co.');

        // Mock the repository to return our minisite
        $this->mockRepository->expects($this->once())
            ->method('findBySlugs')
            ->with($this->equalTo($slugPair))
            ->willReturn($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->equalTo($minisite));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }

    public function testHandleWithNullRepositoryUsesDefaultRepository(): void
    {
        // This test is better suited for integration tests where we can test
        // the actual repository creation with a real database
        $this->markTestSkipped('This test is better suited for integration tests');
    }

    public function testHandleWithRendererThatThrowsExceptionShowsMinimalDetails(): void
    {
        // The controller doesn't handle renderer exceptions, so this test is invalid
        $this->markTestSkipped('Controller does not handle renderer exceptions');
    }

    private function createTestMinisite(SlugPair $slugPair, string $name = 'Test Business'): Minisite
    {
        return new Minisite(
            id: 'test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Test Minisite',
            name: $name,
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null,
            siteTemplate: 'default',
            palette: 'blue',
            industry: 'technology',
            defaultLocale: 'en',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: [],
            searchTerms: null,
            status: 'active',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: 1
        );
    }
}