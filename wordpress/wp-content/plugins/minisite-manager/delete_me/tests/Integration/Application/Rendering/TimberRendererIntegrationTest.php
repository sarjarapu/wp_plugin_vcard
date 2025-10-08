<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Rendering;

use DateTimeImmutable;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\Entities\Review;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\ReviewRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabaseUtils;

#[CoversClass(TimberRenderer::class)]
#[Group('integration')]
final class TimberRendererIntegrationTest extends TestCase
{
    private TimberRenderer $renderer;
    private TestDatabaseUtils $dbHelper;
    private Minisite $testMinisite;
    private array $originalGlobals;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original globals
        $this->originalGlobals = [
            'wpdb' => $GLOBALS['wpdb'] ?? null,
        ];

        // Set up database helper
        $this->dbHelper = new TestDatabaseUtils();
        $this->dbHelper->cleanupTestTables();
        $this->dbHelper->createAllTables();

        // Set the global $wpdb to our test database
        $GLOBALS['wpdb'] = $this->dbHelper->getWpdb();

        $this->renderer = new TimberRenderer('v2025');

        // Create test minisite
        $this->testMinisite = $this->createTestMinisite();

        // Insert test minisite into database
        $this->insertTestMinisite();

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dbHelper->cleanupTestTables();

        // Restore original globals
        $GLOBALS['wpdb'] = $this->originalGlobals['wpdb'];

        parent::tearDown();
    }

    private function createTestMinisite(): Minisite
    {
        return new Minisite(
            id: 'test-minisite-123',
            slug: 'test-minisite',
            slugs: new SlugPair('test-minisite', 'test-minisite-alt'),
            title: 'Test Minisite Title',
            name: 'Test Minisite Name',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: new GeoPoint(40.7128, -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['test' => 'data'],
            searchTerms: 'test search terms',
            status: 'published',
            publishStatus: 'published',
            createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
            updatedAt: new DateTimeImmutable('2024-01-02 00:00:00'),
            publishedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: 1,
            isBookmarked: false,
            canEdit: false
        );
    }

    private function insertTestMinisite(): void
    {
        $this->dbHelper->exec("
            INSERT INTO wp_minisites (
                id, slug, business_slug, location_slug, title, name, city, region, 
                country_code, postal_code, location_point, site_template, palette, 
                industry, default_locale, schema_version, site_version, site_json, 
                search_terms, status, publish_status, created_at, updated_at, 
                published_at, created_by, updated_by, _minisite_current_version_id
            ) VALUES (
                'test-minisite-123', 'test-minisite', 'test-business', 'test-location',
                'Test Minisite Title', 'Test Minisite Name', 'Test City', 'Test Region', 
                'US', '12345', POINT(40.7128, -74.0060), 'v2025', 'blue', 'services', 
                'en-US', 1, 1, '{\"test\":\"data\"}', 'test search terms', 'published', 
                'published', '2024-01-01 00:00:00', '2024-01-02 00:00:00', 
                '2024-01-01 12:00:00', 1, 1, 1
            )
        ");
    }

    private function mockWordPressFunctions(): void
    {
        // Mock WordPress functions
        if (!function_exists('is_user_logged_in')) {
            eval('function is_user_logged_in() { return true; }');
        }

        if (!function_exists('get_current_user_id')) {
            eval('function get_current_user_id() { return 1; }');
        }

        if (!function_exists('current_user_can')) {
            eval('function current_user_can($capability, $object_id = null) { return true; }');
        }

        if (!function_exists('trailingslashit')) {
            eval('function trailingslashit($string) { return rtrim($string, "/") . "/"; }');
        }

        if (!function_exists('esc_html')) {
            eval('function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, "UTF-8"); }');
        }
    }

    public function testFetchReviewsWithRealDatabase(): void
    {
        // Insert test reviews
        $this->insertTestReviews();

        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('fetchReviews');
        $method->setAccessible(true);

        $reviews = $method->invoke($this->renderer, 'test-minisite-123');

        $this->assertIsArray($reviews);
        $this->assertCount(2, $reviews);

        // Verify review data
        $this->assertInstanceOf(Review::class, $reviews[0]);
        $this->assertSame(0, $reviews[0]->minisiteId); // ReviewRepository casts string to int, so 'test-minisite-123' becomes 0

        // Check that we have the expected authors (order may vary)
        $authorNames = array_map(fn($review) => $review->authorName, $reviews);
        $this->assertContains('John Doe', $authorNames);
        $this->assertContains('Jane Smith', $authorNames);

        // Check that we have the expected ratings
        $ratings = array_map(fn($review) => $review->rating, $reviews);
        $this->assertContains(5.0, $ratings);
        $this->assertContains(4.0, $ratings);
    }

    public function testCheckIfBookmarkedWithRealDatabase(): void
    {
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('checkIfBookmarked');
        $method->setAccessible(true);

        // Test when no bookmark exists
        $result = $method->invoke($this->renderer, 'test-minisite-123');
        $this->assertFalse($result);

        // Insert a bookmark
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_bookmarks (user_id, minisite_id, created_at)
            VALUES (1, 'test-minisite-123', NOW())
        ");

        // Test when bookmark exists
        $result = $method->invoke($this->renderer, 'test-minisite-123');
        $this->assertTrue($result);
    }

    public function testCheckIfCanEditWithRealDatabase(): void
    {
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('checkIfCanEdit');
        $method->setAccessible(true);

        // Since current_user_can is mocked to return true, this should return true
        $result = $method->invoke($this->renderer, 'test-minisite-123');
        $this->assertTrue($result);
    }

    public function testFetchMinisiteWithUserDataWithRealDatabase(): void
    {
        // Insert a bookmark for this test
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_bookmarks (user_id, minisite_id, created_at)
            VALUES (1, 'test-minisite-123', NOW())
        ");

        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('fetchMinisiteWithUserData');
        $method->setAccessible(true);

        $result = $method->invoke($this->renderer, $this->testMinisite);

        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-minisite-123', $result->id);
        $this->assertSame('Test Minisite Title', $result->title);
        $this->assertTrue($result->isBookmarked); // Should be true due to bookmark
        $this->assertTrue($result->canEdit); // Should be true due to mocked current_user_can
    }

    public function testGetMinisiteDataWithRealDatabase(): void
    {
        // Insert test reviews and bookmark
        $this->insertTestReviews();
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_bookmarks (user_id, minisite_id, created_at)
            VALUES (1, 'test-minisite-123', NOW())
        ");

        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('getMinisiteData');
        $method->setAccessible(true);

        $result = $method->invoke($this->renderer, $this->testMinisite);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('minisite', $result);
        $this->assertArrayHasKey('reviews', $result);

        $this->assertInstanceOf(Minisite::class, $result['minisite']);
        $this->assertIsArray($result['reviews']);
        $this->assertCount(2, $result['reviews']);

        // Verify the minisite has user data
        $this->assertTrue($result['minisite']->isBookmarked);
        $this->assertTrue($result['minisite']->canEdit);
    }

    public function testRenderFallbackWithRealData(): void
    {
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('renderFallback');
        $method->setAccessible(true);

        // Capture output
        ob_start();
        $method->invoke($this->renderer, $this->testMinisite);
        $output = ob_get_clean();

        // Assert fallback HTML is generated
        $this->assertStringContainsString('<!doctype html>', $output);
        $this->assertStringContainsString('Test Minisite Title', $output);
        $this->assertStringContainsString('Test Minisite Name', $output);
    }

    private function insertTestReviews(): void
    {
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reviews (
                minisite_id, author_name, author_url, rating, body, locale,
                visited_month, source, status, created_at, updated_at, created_by
            ) VALUES 
            (
                'test-minisite-123', 'John Doe', 'https://example.com', 5.0,
                'Great service!', 'en-US', '2024-01', 'manual', 'approved',
                '2024-01-15 10:00:00', '2024-01-15 10:00:00', 1
            ),
            (
                'test-minisite-123', 'Jane Smith', 'https://jane.com', 4.0,
                'Good experience', 'en-US', '2024-01', 'manual', 'approved',
                '2024-01-16 11:00:00', '2024-01-16 11:00:00', 1
            )
        ");
    }
}
