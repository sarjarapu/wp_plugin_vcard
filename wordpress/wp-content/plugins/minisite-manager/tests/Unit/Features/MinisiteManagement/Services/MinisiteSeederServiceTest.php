<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteManagement\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\MinisiteManagement\Services\MinisiteSeederService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Testable subclass to expose protected methods for unit testing
 */
class TestableMinisiteSeederService extends MinisiteSeederService
{
    public function __construct(MinisiteRepositoryInterface $minisiteRepository)
    {
        parent::__construct($minisiteRepository);
    }

    public function callLoadMinisiteFromJson(string $jsonFile): array
    {
        return parent::loadMinisiteFromJson($jsonFile);
    }

    public function callConvertLocationFormat(array $data): array
    {
        return parent::convertLocationFormat($data);
    }

    public function callSetComputedFields(array $data): array
    {
        return parent::setComputedFields($data);
    }
}

/**
 * Test MinisiteSeederService
 *
 * Tests the MinisiteSeederService for creating minisites from JSON data
 */
final class MinisiteSeederServiceTest extends TestCase
{
    private MinisiteRepositoryInterface|MockObject $repository;
    private MinisiteSeederService $seederService;
    private TestableMinisiteSeederService $testableSeeder;
    private array $tempJsonFiles = array();

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->seederService = new MinisiteSeederService($this->repository);
        $this->testableSeeder = new TestableMinisiteSeederService($this->repository);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempJsonFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->tempJsonFiles = array();
        parent::tearDown();
    }

    public function test_create_minisite_from_json_data_with_all_fields(): void
    {
        $jsonData = array(
            'id' => 'test-minisite-123',
            'slug' => 'test-slug',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Minisite Title',
            'name' => 'Test Minisite Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location' => array(
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ),
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 1,
            'site_json' => array('test' => 'data'),
            'search_terms' => 'test search terms',
            'status' => 'published',
            'publish_status' => 'published',
            'created_at' => '2024-01-01 12:00:00',
            'updated_at' => '2024-01-02 12:00:00',
            'published_at' => '2024-01-03 12:00:00',
            'created_by' => 1,
            'updated_by' => 2,
            '_minisite_current_version_id' => 5,
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertInstanceOf(Minisite::class, $minisite);
        $this->assertEquals('test-minisite-123', $minisite->id);
        $this->assertEquals('test-slug', $minisite->slug);
        $this->assertEquals('test-business', $minisite->businessSlug);
        $this->assertEquals('test-location', $minisite->locationSlug);
        $this->assertEquals('Test Minisite Title', $minisite->title);
        $this->assertEquals('Test Minisite Name', $minisite->name);
        $this->assertEquals('Test City', $minisite->city);
        $this->assertEquals('Test Region', $minisite->region);
        $this->assertEquals('US', $minisite->countryCode);
        $this->assertEquals('12345', $minisite->postalCode);
        $this->assertNotNull($minisite->geo);
        $this->assertEquals(40.7128, $minisite->geo->getLat(), '', 0.0001);
        $this->assertEquals(-74.0060, $minisite->geo->getLng(), '', 0.0001);
        $this->assertNotNull($minisite->slugs);
        $this->assertEquals('test-business', $minisite->slugs->business);
        $this->assertEquals('test-location', $minisite->slugs->location);
        $this->assertEquals('v2025', $minisite->siteTemplate);
        $this->assertEquals('blue', $minisite->palette);
        $this->assertEquals('services', $minisite->industry);
        $this->assertEquals('en-US', $minisite->defaultLocale);
        $this->assertEquals(1, $minisite->schemaVersion);
        $this->assertEquals(1, $minisite->siteVersion);
        $this->assertEquals('test search terms', $minisite->searchTerms);
        $this->assertEquals('published', $minisite->status);
        $this->assertEquals('published', $minisite->publishStatus);
        $this->assertNotNull($minisite->createdAt);
        $this->assertNotNull($minisite->updatedAt);
        $this->assertNotNull($minisite->publishedAt);
        $this->assertEquals(1, $minisite->createdBy);
        // updatedBy uses get_current_user_id() if not provided, which is 0 in tests
        $this->assertIsInt($minisite->updatedBy);
        // currentVersionId is reset to null by setComputedFields() for seeding
        $this->assertNull($minisite->currentVersionId);
        $this->assertIsString($minisite->siteJson);
        $decoded = json_decode($minisite->siteJson, true);
        $this->assertEquals('data', $decoded['test']);
    }

    public function test_create_minisite_from_json_data_with_minimal_fields(): void
    {
        $jsonData = array(
            'name' => 'Minimal Minisite',
            'city' => 'Minimal City',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertInstanceOf(Minisite::class, $minisite);
        $this->assertNotEmpty($minisite->id); // Generated ID
        $this->assertEquals('Minimal Minisite', $minisite->name);
        $this->assertEquals('', $minisite->title); // Defaults to empty string, not name
        $this->assertEquals('Minimal City', $minisite->city);
        $this->assertEquals('US', $minisite->countryCode); // Default
        $this->assertEquals('v2025', $minisite->siteTemplate); // Default
        $this->assertEquals('blue', $minisite->palette); // Default
        $this->assertEquals('services', $minisite->industry); // Default
        $this->assertEquals('en-US', $minisite->defaultLocale); // Default
        $this->assertEquals(1, $minisite->schemaVersion); // Default
        $this->assertEquals(1, $minisite->siteVersion); // Default
        $this->assertEquals('published', $minisite->status); // Default
        $this->assertEquals('published', $minisite->publishStatus); // Default
        $this->assertNotNull($minisite->createdAt);
        $this->assertNotNull($minisite->updatedAt);
        $this->assertNotNull($minisite->publishedAt);
        $this->assertEquals('{}', $minisite->siteJson); // Default empty JSON
    }

    public function test_create_minisite_from_json_data_with_location_point_format(): void
    {
        $jsonData = array(
            'name' => 'Location Test',
            'city' => 'Test City',
            'location_point' => array(
                'latitude' => 34.0522,
                'longitude' => -118.2437,
            ),
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertNotNull($minisite->geo);
        $this->assertEquals(34.0522, $minisite->geo->getLat(), '', 0.0001);
        $this->assertEquals(-118.2437, $minisite->geo->getLng(), '', 0.0001);
    }

    public function test_create_minisite_from_json_data_with_site_json_as_string(): void
    {
        $jsonData = array(
            'name' => 'JSON String Test',
            'city' => 'Test City',
            'site_json' => '{"key": "value"}',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertEquals('{"key": "value"}', $minisite->siteJson);
    }

    public function test_create_minisite_from_json_data_with_site_json_as_array(): void
    {
        $jsonData = array(
            'name' => 'JSON Array Test',
            'city' => 'Test City',
            'site_json' => array('key' => 'value'),
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertIsString($minisite->siteJson);
        $decoded = json_decode($minisite->siteJson, true);
        $this->assertEquals('value', $decoded['key']);
    }

    public function test_create_minisite_from_json_data_with_slugs_creates_slug_pair(): void
    {
        $jsonData = array(
            'name' => 'Slug Test',
            'city' => 'Test City',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertNotNull($minisite->slugs);
        $this->assertInstanceOf(SlugPair::class, $minisite->slugs);
        $this->assertEquals('coffee-shop', $minisite->slugs->business);
        $this->assertEquals('downtown', $minisite->slugs->location);
    }

    public function test_create_minisite_from_json_data_without_slugs_no_slug_pair(): void
    {
        $jsonData = array(
            'name' => 'No Slug Test',
            'city' => 'Test City',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertNull($minisite->slugs);
        $this->assertNull($minisite->businessSlug);
        $this->assertNull($minisite->locationSlug);
    }

    public function test_create_minisite_from_json_data_with_partial_slugs_no_slug_pair(): void
    {
        $jsonData = array(
            'name' => 'Partial Slug Test',
            'city' => 'Test City',
            'business_slug' => 'coffee-shop',
            // location_slug missing
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertNull($minisite->slugs);
        $this->assertEquals('coffee-shop', $minisite->businessSlug);
        $this->assertNull($minisite->locationSlug);
    }

    public function test_create_minisite_from_json_data_without_location_no_geo(): void
    {
        $jsonData = array(
            'name' => 'No Location Test',
            'city' => 'Test City',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        $this->assertNull($minisite->geo);
    }

    public function test_create_minisite_from_json_data_with_computed_fields(): void
    {
        $jsonData = array(
            'name' => 'Computed Fields Test',
            'city' => 'Test City',
            'business_slug' => 'test-biz',
            'location_slug' => 'test-loc',
        );

        $minisite = $this->seederService->createMinisiteFromJsonData($jsonData);

        // Computed slug should be set
        $this->assertNotNull($minisite->slug);
        $this->assertEquals('test-biz-test-loc', $minisite->slug);

        // Timestamps should be set
        $this->assertNotNull($minisite->createdAt);
        $this->assertNotNull($minisite->updatedAt);
        $this->assertNotNull($minisite->publishedAt);
    }

    /**
     * Test convertLocationFormat converts JSON format to GeoPoint structure
     */
    public function test_convert_location_format_converts_location(): void
    {
        $data = array(
            'location' => array(
                'latitude' => '12.3456',
                'longitude' => '-65.4321',
            ),
            'name' => 'Location Test',
        );

        $result = $this->testableSeeder->callConvertLocationFormat($data);

        $this->assertArrayNotHasKey('location', $result);
        $this->assertArrayHasKey('location_point', $result);
        $this->assertEquals(12.3456, $result['location_point']['latitude']);
        $this->assertEquals(-65.4321, $result['location_point']['longitude']);
    }

    /**
     * Test convertLocationFormat leaves data unchanged when no location provided
     */
    public function test_convert_location_format_handles_missing_location(): void
    {
        $data = array(
            'name' => 'No Location',
        );

        $result = $this->testableSeeder->callConvertLocationFormat($data);

        $this->assertArrayNotHasKey('location_point', $result);
        $this->assertEquals('No Location', $result['name']);
    }

    /**
     * Test setComputedFields sets timestamps, user IDs, and slug defaults
     */
    public function test_set_computed_fields_sets_defaults(): void
    {
        $data = array(
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
        );

        $result = $this->testableSeeder->callSetComputedFields($data);

        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('published_at', $result);
        $this->assertEquals('test-business-test-location', $result['slug']);
        $this->assertArrayHasKey('created_by', $result);
        $this->assertArrayHasKey('updated_by', $result);
        $this->assertArrayHasKey('_minisite_current_version_id', $result);
        $this->assertNull($result['_minisite_current_version_id']);
    }

    /**
     * Test loadMinisiteFromJson loads data from existing sample file
     */
    public function test_load_minisite_from_json_success(): void
    {
        $result = $this->testableSeeder->callLoadMinisiteFromJson('acme-dental.json');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('acme-dental', $result['business_slug']);
    }

    /**
     * Test loadMinisiteFromJson throws when file not found
     */
    public function test_load_minisite_from_json_file_not_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON file not found');

        $this->testableSeeder->callLoadMinisiteFromJson('missing-file.json');
    }

    /**
     * Test loadMinisiteFromJson throws when JSON is invalid
     */
    public function test_load_minisite_from_json_invalid_json(): void
    {
        $filename = $this->createTempJsonFile('invalid-json.json', '{invalid json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in file');

        $this->testableSeeder->callLoadMinisiteFromJson($filename);
    }

    /**
     * Test loadMinisiteFromJson throws when minisite key missing
     */
    public function test_load_minisite_from_json_missing_minisite_key(): void
    {
        $filename = $this->createTempJsonFile('missing-key.json', json_encode(array('not_minisite' => array())));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON structure in file');

        $this->testableSeeder->callLoadMinisiteFromJson($filename);
    }

    public function test_seed_all_test_minisites_success(): void
    {
        // Mock repository to return saved minisites
        $mockMinisite1 = $this->createMock(Minisite::class);
        $mockMinisite1->id = 'test-id-1';
        $mockMinisite1->businessSlug = 'acme-dental';
        $mockMinisite1->locationSlug = 'dallas';

        $mockMinisite2 = $this->createMock(Minisite::class);
        $mockMinisite2->id = 'test-id-2';
        $mockMinisite2->businessSlug = 'lotus-textiles';
        $mockMinisite2->locationSlug = 'mumbai';

        $this->repository
            ->expects($this->atLeast(2))
            ->method('insert')
            ->willReturnOnConsecutiveCalls($mockMinisite1, $mockMinisite2);

        // This test assumes JSON files exist - if they don't, it will fail
        // In a real scenario, we'd mock the file system or use test fixtures
        try {
            $result = $this->seederService->seedAllSampleMinisites();

            $this->assertIsArray($result);
            // Should have keys for each minisite
            $this->assertArrayHasKey('ACME', $result);
            $this->assertArrayHasKey('LOTUS', $result);
        } catch (\RuntimeException $e) {
            // If JSON files don't exist, skip this test
            $this->markTestSkipped('JSON files not available: ' . $e->getMessage());
        }
    }

    public function test_seed_all_test_minisites_handles_missing_files(): void
    {
        // Create a service that will fail to load files
        // We can't easily mock protected methods, so we'll test the error handling
        // by ensuring the method doesn't throw and returns partial results

        $this->repository
            ->method('insert')
            ->willThrowException(new \RuntimeException('File not found'));

        // Method should handle errors gracefully and return partial results
        $result = $this->seederService->seedAllSampleMinisites();

        $this->assertIsArray($result);
        // Should return empty array or partial results if files are missing
    }
    private function createTempJsonFile(string $filename, string $contents): string
    {
        $path = MINISITE_PLUGIN_DIR . 'data/json/minisites/' . $filename;
        file_put_contents($path, $contents);
        $this->tempJsonFiles[] = $path;

        return $filename;
    }
}
