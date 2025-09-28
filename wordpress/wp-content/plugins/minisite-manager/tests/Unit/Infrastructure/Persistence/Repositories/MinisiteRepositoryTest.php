<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

#[CoversClass(MinisiteRepository::class)]
final class MinisiteRepositoryTest extends TestCase
{
    private MinisiteRepository $repository;
    private FakeWpdb $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(FakeWpdb::class);
        $this->mockDb->prefix = 'wp_';
        $this->repository = new MinisiteRepository($this->mockDb);
    }

    public function testTableReturnsCorrectTableName(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('table');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->repository);
        
        $this->assertSame('wp_minisites', $result);
    }

    public function testFindBySlugsReturnsMinisiteWhenFound(): void
    {
        $slugs = new SlugPair('test-business', 'test-location');
        $row = $this->createTestRow();
        
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) use ($row) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return $row;
            });

        $result = $this->repository->findBySlugs($slugs);
        
        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-123', $result->id);
        $this->assertSame('Test Business', $result->title);
        $this->assertSame('test-business', $result->slugs->business);
        $this->assertSame('test-location', $result->slugs->location);
    }

    public function testFindBySlugsReturnsNullWhenNotFound(): void
    {
        $slugs = new SlugPair('nonexistent', 'location');
        
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn(null);

        $result = $this->repository->findBySlugs($slugs);
        
        $this->assertNull($result);
    }

    public function testFindBySlugParamsReturnsMinisiteWithForUpdate(): void
    {
        $row = $this->createTestRow();
        
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) use ($row) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return $row;
            });

        $result = $this->repository->findBySlugParams('test-business', 'test-location');
        
        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-123', $result->id);
    }

    public function testFindByIdReturnsMinisiteWhenFound(): void
    {
        $row = $this->createTestRow();
        
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) use ($row) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return $row;
            });

        $result = $this->repository->findById('test-123');
        
        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-123', $result->id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_row')
            ->with('prepared query', \ARRAY_A)
            ->willReturn(null);

        $result = $this->repository->findById('nonexistent');
        
        $this->assertNull($result);
    }

    public function testListByOwnerReturnsArrayOfMinisites(): void
    {
        $rows = [
            $this->createTestRow(['id' => 'test-1', 'title' => 'Business 1']),
            $this->createTestRow(['id' => 'test-2', 'title' => 'Business 2'])
        ];
        
        $this->mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($rows);

        // Mock spatial queries for each row
        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return null;
            });

        $result = $this->repository->listByOwner(123);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Minisite::class, $result[0]);
        $this->assertInstanceOf(Minisite::class, $result[1]);
        $this->assertSame('test-1', $result[0]->id);
        $this->assertSame('test-2', $result[1]->id);
    }

    public function testListByOwnerWithCustomLimitAndOffset(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                'SELECT * FROM wp_minisites WHERE created_by=%d ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
                456,
                10,
                20
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn([]);

        $result = $this->repository->listByOwner(456, 10, 20);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUpdateCurrentVersionIdSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                'UPDATE wp_minisites SET _minisite_current_version_id = %d WHERE id = %s',
                456,
                'test-123'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updateCurrentVersionId('test-123', 456);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdateCurrentVersionIdThrowsExceptionWhenNoRowsAffected(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found or update failed.');
        
        $this->repository->updateCurrentVersionId('nonexistent', 456);
    }

    public function testUpdateCoordinatesSuccess(): void
    {
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updateCoordinates('test-123', 40.7128, -74.0060, 123);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdateCoordinatesWithNullValues(): void
    {
        $this->mockDb->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updateCoordinates('test-123', null, null, 123);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdateSlugSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                'UPDATE wp_minisites SET slug=%s, updated_at=NOW() WHERE id=%s',
                'new-slug',
                'test-123'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updateSlug('test-123', 'new-slug');
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdateSlugsSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                'UPDATE wp_minisites SET business_slug=%s, location_slug=%s, updated_at=NOW() WHERE id=%s',
                'new-business',
                'new-location',
                'test-123'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updateSlugs('test-123', 'new-business', 'new-location');
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdatePublishStatusSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                'UPDATE wp_minisites SET publish_status=%s, updated_at=NOW() WHERE id=%s',
                'published',
                'test-123'
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        $this->repository->updatePublishStatus('test-123', 'published');
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testInsertMinisiteSuccess(): void
    {
        $minisite = $this->createTestMinisite();
        
        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisites',
                $this->callback(function ($data) {
                    return $data['id'] === 'test-123' &&
                           $data['business_slug'] === 'test-business' &&
                           $data['location_slug'] === 'test-location' &&
                           $data['title'] === 'Test Business' &&
                           $data['name'] === 'Test Business Name' &&
                           $data['city'] === 'New York' &&
                           $data['region'] === 'NY' &&
                           $data['country_code'] === 'US' &&
                           $data['postal_code'] === '10001' &&
                           $data['site_template'] === 'v2025' &&
                           $data['palette'] === 'blue' &&
                           $data['industry'] === 'services' &&
                           $data['default_locale'] === 'en-US' &&
                           $data['schema_version'] === 1 &&
                           $data['site_version'] === 1 &&
                           $data['status'] === 'draft' &&
                           $data['created_by'] === 123 &&
                           $data['updated_by'] === 123;
                }),
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d']
            )
            ->willReturn(1);

        // Mock the spatial update query
        $this->mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        // Mock the findById call that returns the inserted minisite
        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return $this->createTestRow();
            });

        $result = $this->repository->insert($minisite);
        
        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-123', $result->id);
    }

    public function testInsertMinisiteThrowsExceptionOnFailure(): void
    {
        $minisite = $this->createTestMinisite();
        
        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to insert minisite.');
        
        $this->repository->insert($minisite);
    }

    public function testSaveMinisiteSuccess(): void
    {
        $minisite = $this->createTestMinisite();
        $expectedSiteVersion = 1;
        
        $this->mockDb->expects($this->exactly(4))
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->exactly(2))
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 1;

        // Mock the findBySlugs call that returns the updated minisite
        $this->mockDb->expects($this->exactly(2))
            ->method('get_row')
            ->willReturnCallback(function($query, $output) {
                if (strpos($query, 'SELECT ST_Y(location_point) as lng, ST_X(location_point) as lat') !== false) {
                    return ['lat' => '40.7128', 'lng' => '-74.0060'];
                }
                return $this->createTestRow();
            });

        $result = $this->repository->save($minisite, $expectedSiteVersion);
        
        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertSame('test-123', $result->id);
    }

    public function testSaveMinisiteThrowsExceptionOnConcurrentModification(): void
    {
        $minisite = $this->createTestMinisite();
        $expectedSiteVersion = 1;
        
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('query')
            ->with('prepared query');

        $this->mockDb->rows_affected = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected (optimistic lock failed).');
        
        $this->repository->save($minisite, $expectedSiteVersion);
    }

    public function testUpdateTitleSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                'wp_minisites',
                ['title' => 'New Title'],
                ['id' => 'test-123'],
                ['%s'],
                ['%s']
            )
            ->willReturn(1);

        $result = $this->repository->updateTitle('test-123', 'New Title');
        
        $this->assertTrue($result);
    }

    public function testUpdateTitleFailure(): void
    {
        $this->mockDb->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $result = $this->repository->updateTitle('test-123', 'New Title');
        
        $this->assertFalse($result);
    }

    public function testUpdateStatusSuccess(): void
    {
        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                'wp_minisites',
                $this->callback(function ($data) {
                    return $data['status'] === 'published' && isset($data['published_at']);
                }),
                ['id' => 'test-123'],
                ['%s', '%s'],
                ['%s']
            )
            ->willReturn(1);

        $result = $this->repository->updateStatus('test-123', 'published');
        
        $this->assertTrue($result);
    }

    public function testUpdateBusinessInfoSuccess(): void
    {
        $fields = [
            'title' => 'New Title',
            'name' => 'New Name',
            'city' => 'New City'
        ];
        
        $this->mockDb->expects($this->once())
            ->method('update')
            ->with(
                'wp_minisites',
                $this->callback(function ($data) {
                    return $data['updated_by'] === 123 &&
                           isset($data['updated_at']) &&
                           $data['title'] === 'New Title' &&
                           $data['name'] === 'New Name' &&
                           $data['city'] === 'New City';
                }),
                ['id' => 'test-123'],
                ['%d', '%s', '%s', '%s', '%s'],
                ['%s']
            )
            ->willReturn(1);

        $this->repository->updateBusinessInfo('test-123', $fields, 123);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testUpdateBusinessInfoThrowsExceptionOnFailure(): void
    {
        $fields = ['title' => 'New Title'];
        
        $this->mockDb->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to update business info fields.');
        
        $this->repository->updateBusinessInfo('test-123', $fields, 123);
    }

    public function testPublishMinisiteSuccess(): void
    {
        // This test is complex due to VersionRepository being final and global $wpdb usage
        // It's better tested in integration tests where we can use real dependencies
        $this->markTestSkipped('Complex test with final class dependencies - tested in integration tests');
    }

    private function createTestMinisite(): Minisite
    {
        return new Minisite(
            id: 'test-123',
            slugs: new SlugPair('test-business', 'test-location'),
            title: 'Test Business',
            name: 'Test Business Name',
            city: 'New York',
            region: 'NY',
            countryCode: 'US',
            postalCode: '10001',
            geo: new GeoPoint(40.7128, -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['test' => 'data'],
            searchTerms: 'test business name new york blue test business',
            status: 'draft',
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: new DateTimeImmutable('2025-01-15T10:30:00Z'),
            publishedAt: null,
            createdBy: 123,
            updatedBy: 123,
            currentVersionId: null
        );
    }

    private function createTestVersion(): Version
    {
        return new Version(
            id: 1,
            minisiteId: 'test-123',
            versionNumber: 1,
            status: 'draft',
            label: 'Test Version',
            comment: 'Test comment',
            createdBy: 123,
            publishedAt: null,
            sourceVersionId: null,
            slugs: new SlugPair('test-business', 'test-location'),
            title: 'Test Business',
            name: 'Test Business Name',
            city: 'New York',
            region: 'NY',
            countryCode: 'US',
            postalCode: '10001',
            geo: new GeoPoint(40.7128, -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['test' => 'data'],
            searchTerms: 'test business name new york blue test business'
        );
    }

    private function createTestRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 'test-123',
            'slug' => 'test-slug',
            'business_slug' => 'test-business',
            'location_slug' => 'test-location',
            'title' => 'Test Business',
            'name' => 'Test Business Name',
            'city' => 'New York',
            'region' => 'NY',
            'country_code' => 'US',
            'postal_code' => '10001',
            'location_point' => 'POINT(-74.0060 40.7128)',
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => '1',
            'site_version' => '1',
            'site_json' => '{"test": "data"}',
            'search_terms' => 'test business name new york blue test business',
            'status' => 'draft',
            'publish_status' => 'draft',
            'created_at' => '2025-01-15 10:00:00',
            'updated_at' => '2025-01-15 10:30:00',
            'published_at' => null,
            'created_by' => '123',
            'updated_by' => '123',
            '_minisite_current_version_id' => null
        ], $overrides);
    }
}
