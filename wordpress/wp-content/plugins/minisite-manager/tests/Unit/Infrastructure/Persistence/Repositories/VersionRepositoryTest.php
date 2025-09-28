<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

#[CoversClass(VersionRepository::class)]
final class VersionRepositoryTest extends TestCase
{
    private VersionRepository $repository;
    private FakeWpdb $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(FakeWpdb::class);
        $this->mockDb->prefix = 'wp_';
        $this->repository = new VersionRepository($this->mockDb);
    }

    public function testTableReturnsCorrectTableName(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('table');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->repository);
        
        $this->assertSame('wp_minisite_versions', $result);
    }

    public function testSaveInsertsNewVersion(): void
    {
        $version = $this->createTestVersion();
        $version->id = null; // New version
        
        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisite_versions',
                $this->callback(function ($data) {
                    return $data['minisite_id'] === 'test-minisite' &&
                           $data['version_number'] === 1 &&
                           $data['status'] === 'draft' &&
                           $data['label'] === 'Test Version' &&
                           $data['comment'] === 'Test comment' &&
                           $data['created_by'] === 1 &&
                           $data['business_slug'] === 'test-business' &&
                           $data['location_slug'] === 'test-location' &&
                           $data['title'] === 'Test Title' &&
                           $data['name'] === 'Test Name' &&
                           $data['city'] === 'Test City' &&
                           $data['region'] === 'Test Region' &&
                           $data['country_code'] === 'US' &&
                           $data['postal_code'] === '12345' &&
                           $data['site_template'] === 'v2025' &&
                           $data['palette'] === 'blue' &&
                           $data['industry'] === 'services' &&
                           $data['default_locale'] === 'en-US' &&
                           $data['schema_version'] === 1 &&
                           $data['site_version'] === 1 &&
                           $data['search_terms'] === 'test terms';
                }),
                $this->isType('array')
            )
            ->willReturn(1);

        $this->mockDb->insert_id = 123;
        
        $result = $this->repository->save($version);
        
        $this->assertSame(123, $result->id);
    }

    public function testSaveUpdatesExistingVersion(): void
    {
        $version = $this->createTestVersion();
        $version->id = 123; // Existing version
        
        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                'wp_minisite_versions',
                $this->callback(function ($data) {
                    return $data['minisite_id'] === 'test-minisite' &&
                           $data['version_number'] === 1 &&
                           $data['status'] === 'draft';
                }),
                ['id' => 123],
                $this->isType('array'),
                ['%d']
            )
            ->willReturn(1);
        
        $result = $this->repository->save($version);
        
        $this->assertSame(123, $result->id);
    }

    public function testSaveHandlesGeoLocationForNewVersion(): void
    {
        $version = $this->createTestVersion();
        $version->id = null;
        $version->geo = new GeoPoint(40.7128, -74.0060); // NYC coordinates
        
        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(1);
        
        $this->mockDb->insert_id = 123;
        
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE wp_minisite_versions SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d'))
            ->willReturn('geo update query');
        
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('geo update query')
            ->willReturn(1);
        
        $result = $this->repository->save($version);
        
        $this->assertSame(123, $result->id);
    }

    public function testSaveHandlesGeoLocationForExistingVersion(): void
    {
        $version = $this->createTestVersion();
        $version->id = 123;
        $version->geo = new GeoPoint(40.7128, -74.0060);
        
        $this->mockDb->expects($this->once())
            ->method('update')
            ->willReturn(1);
        
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE wp_minisite_versions SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d'))
            ->willReturn('geo update query');
        
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('geo update query')
            ->willReturn(1);
        
        $result = $this->repository->save($version);
        
        $this->assertSame(123, $result->id);
    }

    public function testSaveClearsGeoLocationWhenNull(): void
    {
        $version = $this->createTestVersion();
        $version->id = 123;
        $version->geo = null;
        
        $this->mockDb->expects($this->once())
            ->method('update')
            ->willReturn(1);
        
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE wp_minisite_versions SET location_point = NULL WHERE id = %d'))
            ->willReturn('geo clear query');
        
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('geo clear query')
            ->willReturn(1);
        
        $result = $this->repository->save($version);
        
        $this->assertSame(123, $result->id);
    }

    public function testFindByIdReturnsVersionWhenFound(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '1',
            'status' => 'draft',
            'label' => 'Test Version',
            'comment' => 'Test comment',
            'created_by' => '1',
            'created_at' => '2025-01-01 00:00:00',
            'published_at' => null,
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM wp_minisite_versions WHERE id = %d LIMIT 1'), 123)
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($row);

        $result = $this->repository->findById(123);
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame(123, $result->id);
        $this->assertSame('test-minisite', $result->minisiteId);
        $this->assertSame(1, $result->versionNumber);
        $this->assertSame('draft', $result->status);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->willReturn(null);

        $result = $this->repository->findById(999);
        
        $this->assertNull($result);
    }

    public function testFindByMinisiteIdReturnsArrayOfVersions(): void
    {
        $rows = [
            [
                'id' => '123',
                'minisite_id' => 'test-minisite',
                'version_number' => '2',
                'status' => 'published',
                'label' => 'Version 2',
                'comment' => 'Published version',
                'created_by' => '1',
                'created_at' => '2025-01-02 00:00:00',
                'published_at' => '2025-01-02 00:00:00',
                'source_version_id' => null,
                'business_slug' => 'test-business',
                'location_slug' => 'test-location',
                'title' => 'Test Title',
                'name' => 'Test Name',
                'city' => 'Test City',
                'region' => 'Test Region',
                'country_code' => 'US',
                'postal_code' => '12345',
                'location_point' => null,
                'site_template' => 'v2025',
                'palette' => 'blue',
                'industry' => 'services',
                'default_locale' => 'en-US',
                'schema_version' => '1',
                'site_version' => '1',
                'site_json' => '{"test": "data"}',
                'search_terms' => 'test terms'
            ],
            [
                'id' => '122',
                'minisite_id' => 'test-minisite',
                'version_number' => '1',
                'status' => 'draft',
                'label' => 'Version 1',
                'comment' => 'Draft version',
                'created_by' => '1',
                'created_at' => '2025-01-01 00:00:00',
                'published_at' => null,
                'source_version_id' => null,
                'business_slug' => 'test-business',
                'location_slug' => 'test-location',
                'title' => 'Test Title',
                'name' => 'Test Name',
                'city' => 'Test City',
                'region' => 'Test Region',
                'country_code' => 'US',
                'postal_code' => '12345',
                'location_point' => null,
                'site_template' => 'v2025',
                'palette' => 'blue',
                'industry' => 'services',
                'default_locale' => 'en-US',
                'schema_version' => '1',
                'site_version' => '1',
                'site_json' => '{"test": "data"}',
                'search_terms' => 'test terms'
            ]
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'ORDER BY version_number DESC') !== false &&
                           strpos($query, 'LIMIT %d OFFSET %d') !== false;
                }),
                'test-minisite',
                50,
                0
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($rows);

        $result = $this->repository->findByMinisiteId('test-minisite');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Version::class, $result[0]);
        $this->assertSame(2, $result[0]->versionNumber);
        $this->assertSame(1, $result[1]->versionNumber);
    }

    public function testFindByMinisiteIdWithCustomLimitAndOffset(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'ORDER BY version_number DESC') !== false &&
                           strpos($query, 'LIMIT %d OFFSET %d') !== false;
                }),
                'test-minisite',
                10,
                20
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = $this->repository->findByMinisiteId('test-minisite', 10, 20);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindLatestVersionReturnsLatestVersion(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '5',
            'status' => 'published',
            'label' => 'Latest Version',
            'comment' => 'Latest version',
            'created_by' => '1',
            'created_at' => '2025-01-05 00:00:00',
            'published_at' => '2025-01-05 00:00:00',
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'ORDER BY version_number DESC') !== false &&
                           strpos($query, 'LIMIT 1') !== false;
                }),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($row);

        $result = $this->repository->findLatestVersion('test-minisite');
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame(5, $result->versionNumber);
    }

    public function testFindLatestDraftReturnsLatestDraftVersion(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '3',
            'status' => 'draft',
            'label' => 'Draft Version',
            'comment' => 'Draft version',
            'created_by' => '1',
            'created_at' => '2025-01-03 00:00:00',
            'published_at' => null,
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'AND status = \'draft\'') !== false &&
                           strpos($query, 'ORDER BY version_number DESC') !== false &&
                           strpos($query, 'LIMIT 1') !== false;
                }),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($row);

        $result = $this->repository->findLatestDraft('test-minisite');
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame('draft', $result->status);
    }

    public function testFindPublishedVersionReturnsPublishedVersion(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '2',
            'status' => 'published',
            'label' => 'Published Version',
            'comment' => 'Published version',
            'created_by' => '1',
            'created_at' => '2025-01-02 00:00:00',
            'published_at' => '2025-01-02 00:00:00',
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'AND status = \'published\'') !== false &&
                           strpos($query, 'LIMIT 1') !== false;
                }),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($row);

        $result = $this->repository->findPublishedVersion('test-minisite');
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame('published', $result->status);
    }

    public function testGetNextVersionNumberReturnsNextNumber(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->stringContains('SELECT MAX(version_number) as max_version FROM wp_minisite_versions WHERE minisite_id = %s'),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_var')
            ->with('prepared query')
            ->willReturn('5');

        $result = $this->repository->getNextVersionNumber('test-minisite');
        
        $this->assertSame(6, $result);
    }

    public function testGetNextVersionNumberReturnsOneWhenNoVersions(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_var')
            ->willReturn(null);

        $result = $this->repository->getNextVersionNumber('test-minisite');
        
        $this->assertSame(1, $result);
    }

    public function testDeleteReturnsTrueWhenSuccessful(): void
    {
        $this->mockDb->expects($this->once())
            ->method('delete')
            ->with('wp_minisite_versions', ['id' => 123], ['%d'])
            ->willReturn(1);

        $result = $this->repository->delete(123);
        
        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenUnsuccessful(): void
    {
        $this->mockDb->expects($this->once())
            ->method('delete')
            ->with('wp_minisite_versions', ['id' => 123], ['%d'])
            ->willReturn(false);

        $result = $this->repository->delete(123);
        
        $this->assertFalse($result);
    }

    public function testGetLatestDraftForEditingReturnsExistingDraft(): void
    {
        $draftVersion = $this->createTestVersion();
        $draftVersion->id = 123;
        $draftVersion->status = 'draft';
        $draftVersion->versionNumber = 3;

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_versions') !== false &&
                           strpos($query, 'WHERE minisite_id = %s') !== false &&
                           strpos($query, 'ORDER BY version_number DESC') !== false &&
                           strpos($query, 'LIMIT 1') !== false;
                }),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($this->versionToArray($draftVersion));

        $result = $this->repository->getLatestDraftForEditing('test-minisite');
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame('draft', $result->status);
    }

    public function testGetLatestDraftForEditingCreatesDraftFromPublished(): void
    {
        $publishedVersion = $this->createTestVersion();
        $publishedVersion->id = 123;
        $publishedVersion->status = 'published';
        $publishedVersion->versionNumber = 2;

        // Mock findLatestVersion call
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function($query, $param) {
                if (strpos($query, 'SELECT * FROM wp_minisite_versions') !== false && 
                    strpos($query, 'ORDER BY version_number DESC') !== false) {
                    return 'prepared query 1';
                } elseif (strpos($query, 'SELECT MAX(version_number)') !== false) {
                    return 'prepared query 2';
                }
                return 'default query';
            });

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query 1', \ARRAY_A)
            ->willReturn($this->versionToArray($publishedVersion));

        $this->mockDb->expects($this->once())
            ->method('get_var')
            ->with('prepared query 2')
            ->willReturn('2');

        // Mock save call for new draft
        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockDb->insert_id = 124;

        $result = $this->repository->getLatestDraftForEditing('test-minisite');
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame('draft', $result->status);
        $this->assertSame(3, $result->versionNumber);
        $this->assertSame(123, $result->sourceVersionId);
    }

    public function testGetLatestDraftForEditingThrowsExceptionWhenNoVersions(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No version found for minisite.');

        $this->repository->getLatestDraftForEditing('test-minisite');
    }

    public function testCreateDraftFromVersionCreatesNewDraft(): void
    {
        $sourceVersion = $this->createTestVersion();
        $sourceVersion->id = 123;
        $sourceVersion->status = 'published';
        $sourceVersion->versionNumber = 2;

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->stringContains('SELECT MAX(version_number) as max_version FROM wp_minisite_versions WHERE minisite_id = %s'),
                'test-minisite'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_var')
            ->with('prepared query')
            ->willReturn('2');

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockDb->insert_id = 124;

        $result = $this->repository->createDraftFromVersion($sourceVersion);
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertSame('draft', $result->status);
        $this->assertSame(3, $result->versionNumber);
        $this->assertSame(123, $result->sourceVersionId);
        $this->assertSame('Draft from v2', $result->label);
    }

    public function testMapRowHandlesGeoLocationCorrectly(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '1',
            'status' => 'draft',
            'label' => 'Test Version',
            'comment' => 'Test comment',
            'created_by' => '1',
            'created_at' => '2025-01-01 00:00:00',
            'published_at' => null,
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => 'POINT(-74.006000 40.712800)',
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        // Mock the geo query
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->stringContains('SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat FROM wp_minisite_versions WHERE id = %d'),
                123
            )
            ->willReturn('geo query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('geo query', \ARRAY_A)
            ->willReturn(['lat' => '40.712800', 'lng' => '-74.006000']);

        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('mapRow');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->repository, $row);
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertInstanceOf(GeoPoint::class, $result->geo);
        $this->assertSame(40.7128, $result->geo->lat);
        $this->assertSame(-74.006, $result->geo->lng);
    }

    public function testMapRowHandlesSlugPairCorrectly(): void
    {
        $row = [
            'id' => '123',
            'minisite_id' => 'test-minisite',
            'version_number' => '1',
            'status' => 'draft',
            'label' => 'Test Version',
            'comment' => 'Test comment',
            'created_by' => '1',
            'created_at' => '2025-01-01 00:00:00',
            'published_at' => null,
            'source_version_id' => null,
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Title',
            'name' => 'Test Name',
            'city' => 'Test City',
            'region' => 'Test Region',
            'country_code' => 'US',
            'postal_code' => '12345',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test terms'
        ];

        // No prepare call expected since location_point is null

        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('mapRow');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->repository, $row);
        
        $this->assertInstanceOf(Version::class, $result);
        $this->assertInstanceOf(SlugPair::class, $result->slugs);
        $this->assertSame('test-business', $result->slugs->business);
        $this->assertSame('test-location', $result->slugs->location);
    }

    private function createTestVersion(): Version
    {
        return new Version(
            id: 123,
            minisiteId: 'test-minisite',
            versionNumber: 1,
            status: 'draft',
            label: 'Test Version',
            comment: 'Test comment',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T00:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['test' => 'data'],
            slugs: new SlugPair('test-business', 'test-location'),
            title: 'Test Title',
            name: 'Test Name',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            searchTerms: 'test terms'
        );
    }

    private function versionToArray(Version $version): array
    {
        return [
            'id' => (string) $version->id,
            'minisite_id' => $version->minisiteId,
            'version_number' => (string) $version->versionNumber,
            'status' => $version->status,
            'label' => $version->label,
            'comment' => $version->comment,
            'created_by' => (string) $version->createdBy,
            'created_at' => $version->createdAt?->format('Y-m-d H:i:s'),
            'published_at' => $version->publishedAt?->format('Y-m-d H:i:s'),
            'source_version_id' => $version->sourceVersionId ? (string) $version->sourceVersionId : null,
            'business_slug' => $version->slugs?->business,
            'location_slug' => $version->slugs?->location,
            'title' => $version->title,
            'name' => $version->name,
            'city' => $version->city,
            'region' => $version->region,
            'country_code' => $version->countryCode,
            'postal_code' => $version->postalCode,
            'location_point' => null,
            'site_template' => $version->siteTemplate,
            'palette' => $version->palette,
            'industry' => $version->industry,
            'default_locale' => $version->defaultLocale,
            'schema_version' => $version->schemaVersion ? (string) $version->schemaVersion : null,
            'site_version' => $version->siteVersion ? (string) $version->siteVersion : null,
            'site_json' => json_encode($version->siteJson),
            'search_terms' => $version->searchTerms
        ];
    }
}
