<?php

namespace Tests\Integration\Application\Controllers\Front;

use Minisite\Application\Controllers\Front\MinisitePageController;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Tests\Support\TestDatabaseUtils;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

interface RendererInterface
{
    public function render($minisite): void;
}

#[RunTestsInSeparateProcesses]
final class MinisitePageControllerIntegrationTest extends TestCase
{
    private MinisitePageController $controller;
    private MinisiteRepository $repository;
    private object $mockRenderer;
    private object $mockWpQuery;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test database
        TestDatabaseUtils::setUpTestDatabase();

        // Create real repository with test database
        $this->repository = new MinisiteRepository(db::getWpdb());

        // Create mock renderer
        $this->mockRenderer = $this->createMock(RendererInterface::class);

        // Create mock wp_query
        $this->mockWpQuery = new class {
            public function set_404() { return null; }
        };

        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Create controller with real repository
        $this->controller = new MinisitePageController($this->mockRenderer, $this->repository);
    }

    protected function tearDown(): void
    {
        // Clean up test database
        TestDatabaseUtils::tearDownTestDatabase();

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

    public function testHandleWithExistingMinisiteCallsRenderer(): void
    {
        // Arrange - Create a test minisite in the database
        $businessSlug = 'integration-test-business';
        $locationSlug = 'integration-test-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = new Minisite(
            id: 'integration-test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Integration Test Minisite',
            name: 'Integration Test Business',
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

        // Insert minisite into database
        $this->repository->insert($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->callback(function ($minisite) {
                return $minisite instanceof Minisite && $minisite->id === 'integration-test-id';
            }));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }

    public function testHandleWithNonExistentMinisiteSets404(): void
    {
        // Arrange
        $businessSlug = 'non-existent-business';
        $locationSlug = 'non-existent-location';

        // Mock wp_query set_404 to be called
        // We can't easily mock this in integration tests, so we'll just verify the output

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
        // Arrange - Create a test minisite in the database
        $businessSlug = 'fallback-test-business';
        $locationSlug = 'fallback-test-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = new Minisite(
            id: 'fallback-test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Fallback Test Minisite',
            name: 'Fallback Test Business',
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

        // Insert minisite into database
        $this->repository->insert($minisite);

        // Create a renderer without render method
        $rendererWithoutRender = new \stdClass();

        // Create controller with renderer without render method
        $controller = new MinisitePageController($rendererWithoutRender, $this->repository);

        // Capture output
        ob_start();

        // Act
        $controller->handle($businessSlug, $locationSlug);

        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Fallback Test Business', $output);
    }

    public function testHandleWithSpecialCharactersInSlugs(): void
    {
        // Arrange - Create a test minisite with special characters
        $businessSlug = 'business-with-ümlauts-&-symbols';
        $locationSlug = 'location-with-special-chars';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = new Minisite(
            id: 'special-chars-test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Special Chars Test Minisite',
            name: 'Business & Co. with Ümlauts',
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

        // Insert minisite into database
        $this->repository->insert($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->callback(function ($minisite) {
                return $minisite instanceof Minisite && $minisite->id === 'special-chars-test-id';
            }));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }

    public function testHandleWithEmptySlugs(): void
    {
        // Arrange
        $businessSlug = '';
        $locationSlug = '';

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

    public function testHandleWithPublishedMinisite(): void
    {
        // Arrange - Create a published minisite
        $businessSlug = 'published-business';
        $locationSlug = 'published-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = new Minisite(
            id: 'published-test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Published Test Minisite',
            name: 'Published Test Business',
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

        // Insert minisite into database
        $this->repository->insert($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->callback(function ($minisite) {
                return $minisite instanceof Minisite && 
                       $minisite->id === 'published-test-id';
            }));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }

    public function testHandleWithDraftMinisite(): void
    {
        // Arrange - Create a draft minisite
        $businessSlug = 'draft-business';
        $locationSlug = 'draft-location';
        $slugPair = new SlugPair($businessSlug, $locationSlug);

        $minisite = new Minisite(
            id: 'draft-test-id',
            slug: $slugPair->full(),
            slugs: $slugPair,
            title: 'Draft Test Minisite',
            name: 'Draft Test Business',
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
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: 1
        );

        // Insert minisite into database
        $this->repository->insert($minisite);

        // Mock the renderer to expect render call
        $this->mockRenderer->expects($this->once())
            ->method('render')
            ->with($this->callback(function ($minisite) {
                return $minisite instanceof Minisite && 
                       $minisite->id === 'draft-test-id';
            }));

        // Act
        $this->controller->handle($businessSlug, $locationSlug);

        // Assert - expectations are verified by PHPUnit
        $this->assertTrue(true);
    }
}
