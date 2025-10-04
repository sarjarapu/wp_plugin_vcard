<?php

namespace Minisite\Tests\Integration\Application\Controllers\Front;

use PHPUnit\Framework\TestCase;
use Minisite\Application\Controllers\Front\SitesController;
use Minisite\Application\Controllers\Front\NewMinisiteController;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

/**
 * Integration test for MIN-5: Bug: Location fields not saved when importing JSON data to new minisite draft
 */
class SitesControllerImportLocationTest extends TestCase
{
    private $minisiteRepo;
    private $versionRepo;
    private $newController;
    private $sitesController;
    private $testMinisiteId;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $wpdb;
        
        // Mock WordPress functions
        require_once __DIR__ . '/../../../../test-helpers/wp-mocks.php';
        
        $this->minisiteRepo = new MinisiteRepository($wpdb);
        $this->versionRepo = new VersionRepository($wpdb);
        $this->newController = new NewMinisiteController($this->minisiteRepo, $this->versionRepo);
        $this->sitesController = new SitesController();
    }

    public function testLocationFieldsSavedAfterJsonImport(): void
    {
        // Skip if not in integration test environment
        if (!defined('MINISITE_INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests not enabled');
            return;
        }

        // Arrange: Create a new draft minisite (simulating "Create Free Draft")
        $GLOBALS['test_user_logged_in'] = true;
        $_POST['minisite_nonce'] = 'valid_nonce';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Mock user
        $GLOBALS['test_current_user'] = (object)[
            'ID' => 1,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com'
        ];

        // Capture output to prevent redirect
        ob_start();
        
        try {
            $this->newController->handleCreateSimple();
        } catch (\Exception $e) {
            // Expected to redirect, so we catch and extract minisite ID
        }
        
        ob_end_clean();

        // Get the created minisite ID (would be in redirect URL)
        // For this test, we'll create a minisite directly
        $minisite = new \Minisite\Domain\Entities\Minisite(
            id: 'test-' . uniqid(),
            slug: 'test-business/test-location',
            slugs: new \Minisite\Domain\ValueObjects\SlugPair('test-business', 'test-location'),
            title: 'Test Minisite',
            name: 'Test Business',
            city: '',  // Initially empty
            region: null,  // Initially empty
            countryCode: '',  // Initially empty
            postalCode: null,  // Initially empty
            geo: new \Minisite\Domain\ValueObjects\GeoPoint(0, 0),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'technology',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: [],
            searchTerms: null,
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: null,
            isBookmarked: false,
            canEdit: true
        );

        $savedMinisite = $this->minisiteRepo->insert($minisite);
        $this->testMinisiteId = $savedMinisite->id;

        // Simulate importing JSON data with location fields
        // This would happen via JavaScript in the real flow
        $_POST = [
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Imported Business Name',
            'business_city' => 'San Francisco',  // Imported from JSON
            'business_region' => 'California',  // Imported from JSON
            'business_country' => 'US',  // Imported from JSON
            'business_postal' => '94102',  // Imported from JSON
            'seo_title' => 'Imported SEO Title',
            'brand_palette' => 'blue',
            'brand_industry' => 'technology',
            'site_template' => 'v2025',
            'default_locale' => 'en-US',
            'search_terms' => 'test terms'
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Mock get_query_var to return our test minisite ID
        $GLOBALS['test_query_vars'] = [
            'minisite_site_id' => $this->testMinisiteId
        ];

        // Act: Save the draft with imported data
        ob_start();
        try {
            $this->sitesController->handleEdit();
        } catch (\Exception $e) {
            // Expected to redirect
        }
        ob_end_clean();

        // Assert: Verify location fields were saved
        $updatedMinisite = $this->minisiteRepo->findById($this->testMinisiteId);
        
        // Get the latest version
        $latestVersion = $this->versionRepo->findLatestDraft($this->testMinisiteId);
        
        $this->assertNotNull($latestVersion, 'Latest version should exist');
        $this->assertEquals('San Francisco', $latestVersion->city, 'City should be saved from imported data');
        $this->assertEquals('California', $latestVersion->region, 'Region should be saved from imported data');
        $this->assertEquals('US', $latestVersion->countryCode, 'Country code should be saved from imported data');
        $this->assertEquals('94102', $latestVersion->postalCode, 'Postal code should be saved from imported data');
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->testMinisiteId) {
            // Delete test minisite and versions
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}minisite_versions WHERE minisite_id = %s",
                $this->testMinisiteId
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}minisites WHERE id = %s",
                $this->testMinisiteId
            ));
        }
        
        parent::tearDown();
    }
}
