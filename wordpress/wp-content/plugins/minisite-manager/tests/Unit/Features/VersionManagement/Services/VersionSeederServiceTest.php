<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use Minisite\Features\VersionManagement\Services\VersionSeederService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VersionSeederService
 */
#[CoversClass(VersionSeederService::class)]
final class VersionSeederServiceTest extends TestCase
{
    private VersionRepositoryInterface|MockObject $versionRepository;
    private VersionSeederService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->versionRepository = $this->createMock(VersionRepositoryInterface::class);

        // Use global variable approach instead of Brain Monkey for get_current_user_id
        // since it's already defined in bootstrap.php before Brain Monkey can intercept
        $GLOBALS['_test_mock_get_current_user_id'] = 123;

        $this->service = new VersionSeederService($this->versionRepository);
    }

    protected function tearDown(): void
    {
        // Clean up global mocks
        unset($GLOBALS['_test_mock_get_current_user_id']);

        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(VersionSeederService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('versionRepository', $parameters[0]->getName());
    }

    /**
     * Test createVersionFromJsonData with minimal required fields
     */
    public function test_createVersionFromJsonData_with_minimal_fields(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'versionNumber' => 1,
            'status' => 'draft',
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertInstanceOf(Version::class, $version);
        $this->assertEquals($minisiteId, $version->minisiteId);
        $this->assertEquals(1, $version->versionNumber);
        $this->assertEquals('draft', $version->status);
        $this->assertEquals(123, $version->createdBy); // From mocked get_current_user_id
        $this->assertNotNull($version->createdAt);
        $this->assertNull($version->publishedAt);
        $this->assertEquals('{}', $version->siteJson);
    }

    /**
     * Test createVersionFromJsonData with all fields populated
     */
    public function test_createVersionFromJsonData_with_all_fields(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'versionNumber' => 2,
            'status' => 'published',
            'createdBy' => 456,
            'label' => 'Test Version',
            'comment' => 'Test comment',
            'sourceVersionId' => 1,
            'createdAt' => '2024-01-01 10:00:00',
            'publishedAt' => '2024-01-02 10:00:00',
            'businessSlug' => 'test-business',
            'locationSlug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'countryCode' => 'US',
            'postalCode' => '12345',
            'location' => array(
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ),
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'searchTerms' => 'test search',
            'siteJson' => array('test' => 'data'),
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertInstanceOf(Version::class, $version);
        $this->assertEquals($minisiteId, $version->minisiteId);
        $this->assertEquals(2, $version->versionNumber);
        $this->assertEquals('published', $version->status);
        $this->assertEquals(456, $version->createdBy);
        $this->assertEquals('Test Version', $version->label);
        $this->assertEquals('Test comment', $version->comment);
        $this->assertEquals(1, $version->sourceVersionId);
        $this->assertEquals('2024-01-01 10:00:00', $version->createdAt->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-02 10:00:00', $version->publishedAt->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(SlugPair::class, $version->slugs);
        $this->assertEquals('test-business', $version->slugs->business);
        $this->assertEquals('test-location', $version->slugs->location);
        $this->assertEquals('Test Title', $version->title);
        $this->assertEquals('Test Name', $version->name);
        $this->assertEquals('Test City', $version->city);
        $this->assertEquals('Test Region', $version->region);
        $this->assertEquals('US', $version->countryCode);
        $this->assertEquals('12345', $version->postalCode);
        $this->assertInstanceOf(GeoPoint::class, $version->geo);
        $this->assertEquals(40.7128, $version->geo->getLat());
        $this->assertEquals(-74.0060, $version->geo->getLng());
        $this->assertEquals('v2025', $version->siteTemplate);
        $this->assertEquals('blue', $version->palette);
        $this->assertEquals('services', $version->industry);
        $this->assertEquals('en-US', $version->defaultLocale);
        $this->assertEquals(1, $version->schemaVersion);
        $this->assertEquals(1, $version->siteVersion);
        $this->assertEquals('test search', $version->searchTerms);
        $this->assertStringContainsString('"test":"data"', $version->siteJson);
    }

    /**
     * Test createVersionFromJsonData with default values
     */
    public function test_createVersionFromJsonData_with_defaults(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(); // Empty array

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals(1, $version->versionNumber); // Default
        $this->assertEquals('draft', $version->status); // Default
        $this->assertEquals(123, $version->createdBy); // From mocked get_current_user_id
        $this->assertNull($version->label);
        $this->assertNull($version->comment);
        $this->assertNull($version->sourceVersionId);
        $this->assertNotNull($version->createdAt);
        $this->assertNull($version->publishedAt);
        $this->assertEquals('{}', $version->siteJson);
    }

    /**
     * Test createVersionFromJsonData with published status sets publishedAt to createdAt if not provided
     */
    public function test_createVersionFromJsonData_published_status_sets_publishedAt_to_createdAt(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'status' => 'published',
            // No publishedAt provided
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals('published', $version->status);
        $this->assertNotNull($version->publishedAt);
        $this->assertEquals($version->createdAt->getTimestamp(), $version->publishedAt->getTimestamp());
    }

    /**
     * Test createVersionFromJsonData with latitude/longitude in alternative format
     */
    public function test_createVersionFromJsonData_with_alternative_geo_format(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertInstanceOf(GeoPoint::class, $version->geo);
        $this->assertEquals(40.7128, $version->geo->getLat());
        $this->assertEquals(-74.0060, $version->geo->getLng());
    }

    /**
     * Test createVersionFromJsonData with siteJson as string
     */
    public function test_createVersionFromJsonData_with_siteJson_string(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'siteJson' => '{"test": "data"}',
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals('{"test": "data"}', $version->siteJson);
    }

    /**
     * Test createVersionFromJsonData with siteJson as array
     */
    public function test_createVersionFromJsonData_with_siteJson_array(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'siteJson' => array('test' => 'data'),
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $decoded = json_decode($version->siteJson, true);
        $this->assertEquals(array('test' => 'data'), $decoded);
    }

    /**
     * Test createVersionFromJsonData with createdBy null uses current user
     */
    public function test_createVersionFromJsonData_createdBy_null_uses_current_user(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'createdBy' => null,
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals(123, $version->createdBy); // From mocked get_current_user_id
    }

    /**
     * Test createVersionFromJsonData with createdBy 0 when no current user
     */
    public function test_createVersionFromJsonData_createdBy_0_when_no_current_user(): void
    {
        $GLOBALS['_test_mock_get_current_user_id'] = 0;

        // Recreate service to pick up new user ID
        $this->service = new VersionSeederService($this->versionRepository);

        $minisiteId = 'test-minisite-123';
        $versionData = array(); // No createdBy

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals(0, $version->createdBy);
    }

    /**
     * Test seedVersionsForMinisite saves all versions
     */
    public function t2est_seedVersionsForMinisite_saves_all_versions(): void
    {
        $minisiteId = 'test-minisite-123';
        $versions = array(
            array('versionNumber' => 1, 'status' => 'draft'),
            array('versionNumber' => 2, 'status' => 'published'),
        );

        $this->versionRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($version) {
                return $version instanceof Version;
            }))
            ->willReturnArgument(0);

        $this->service->seedVersionsForMinisite($minisiteId, $versions);
    }

    /**
     * Test seedVersionsForMinisite with empty array
     */
    public function test_seedVersionsForMinisite_with_empty_array(): void
    {
        $minisiteId = 'test-minisite-123';
        $versions = array();

        $this->versionRepository
            ->expects($this->never())
            ->method('save');

        $this->service->seedVersionsForMinisite($minisiteId, $versions);
    }

    /**
     * Test createVersionFromJsonData with createdAt provided
     */
    public function test_createVersionFromJsonData_with_createdAt_provided(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'createdAt' => '2024-01-15 10:30:00',
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals('2024-01-15 10:30:00', $version->createdAt->format('Y-m-d H:i:s'));
    }

    /**
     * Test createVersionFromJsonData with publishedAt provided
     */
    public function test_createVersionFromJsonData_with_publishedAt_provided(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'status' => 'published',
            'publishedAt' => '2024-01-20 15:00:00',
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals('2024-01-20 15:00:00', $version->publishedAt->format('Y-m-d H:i:s'));
    }

    /**
     * Test createVersionFromJsonData with only businessSlug (no SlugPair created)
     */
    public function test_createVersionFromJsonData_with_only_businessSlug(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'businessSlug' => 'test-business',
            // No locationSlug
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertEquals('test-business', $version->businessSlug);
        $this->assertNull($version->locationSlug);
        $this->assertNull($version->slugs); // SlugPair not created when only one slug
    }

    /**
     * Test createVersionFromJsonData with location array but null lat/lng
     */
    public function test_createVersionFromJsonData_with_location_array_null_coords(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'location' => array(
                // No latitude/longitude
            ),
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertNull($version->geo);
    }

    /**
     * Test createVersionFromJsonData with location array with only latitude
     */
    public function test_createVersionFromJsonData_with_location_array_only_latitude(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'location' => array(
                'latitude' => 40.7128,
                // No longitude
            ),
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertNull($version->geo);
    }

    /**
     * Test createVersionFromJsonData with schemaVersion null
     */
    public function test_createVersionFromJsonData_with_schemaVersion_null(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'schemaVersion' => null,
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertNull($version->schemaVersion);
    }

    /**
     * Test createVersionFromJsonData with siteVersion null
     */
    public function test_createVersionFromJsonData_with_siteVersion_null(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'siteVersion' => null,
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        $this->assertNull($version->siteVersion);
    }

    /**
     * Test createVersionFromJsonData with createdAt empty string (should use current time)
     */
    public function test_createVersionFromJsonData_with_createdAt_empty_string(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'createdAt' => '', // Empty string
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        // Should use current time, not empty string
        $this->assertNotNull($version->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $version->createdAt);
    }

    /**
     * Test createVersionFromJsonData with publishedAt empty string (should use createdAt for published)
     */
    public function test_createVersionFromJsonData_with_publishedAt_empty_string_published(): void
    {
        $minisiteId = 'test-minisite-123';
        $versionData = array(
            'status' => 'published',
            'publishedAt' => '', // Empty string
        );

        $version = $this->service->createVersionFromJsonData($minisiteId, $versionData);

        // Should use createdAt since publishedAt is empty
        $this->assertNotNull($version->publishedAt);
        $this->assertEquals($version->createdAt->getTimestamp(), $version->publishedAt->getTimestamp());
    }
}
