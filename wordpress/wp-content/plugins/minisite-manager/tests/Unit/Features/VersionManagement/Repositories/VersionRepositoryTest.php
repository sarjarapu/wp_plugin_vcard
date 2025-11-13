<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VersionRepository
 *
 * Focuses on error paths and exception handling that are difficult to test in integration tests.
 */
#[CoversClass(VersionRepository::class)]
final class VersionRepositoryTest extends TestCase
{
    private VersionRepository $repository;
    private EntityManagerInterface|MockObject $entityManager;
    private Connection|MockObject $connection;
    private ClassMetadata|MockObject $classMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider (required by VersionRepository)
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        // Set required ClassMetadata properties that EntityRepository expects
        $this->classMetadata->name = Version::class;

        $this->classMetadata
            ->method('getTableName')
            ->willReturn('wp_minisite_versions');

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($this->classMetadata);

        $this->repository = new VersionRepository($this->entityManager, $this->classMetadata);
    }

    // ===== find() ERROR PATHS =====

    public function test_find_throws_invalid_argument_exception_for_non_int_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version ID must be an integer');

        $this->repository->find('not-an-int');
    }

    public function test_find_throws_exception_on_database_error(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->find(123);
    }

    // ===== save() ERROR PATHS =====

    public function test_save_throws_exception_on_persist_error(): void
    {
        $version = $this->createVersion();
        $version->id = 123;

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($version)
            ->willThrowException(new \Exception('Persist error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Persist error');

        $this->repository->save($version);
    }

    public function test_save_handles_location_point_update_failure(): void
    {
        $version = $this->createVersion();
        $version->id = 123;
        $version->geo = new GeoPoint(lat: 40.7128, lng: -74.0060);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($version);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Mock findById to return the version after save
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn($version);

        // Location point update returns 0 (no rows affected) - this triggers error log
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('UPDATE'),
                $this->callback(function ($params) {
                    return is_array($params) && count($params) === 3;
                })
            )
            ->willReturn(0); // No rows updated - should log error

        // Should still succeed even if location point update fails
        $result = $this->repository->save($version);

        $this->assertSame($version, $result);
    }

    public function test_save_handles_clearing_location_point_when_no_geo(): void
    {
        $version = $this->createVersion();
        $version->id = 123;
        $version->geo = null; // No geo data

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($version);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Mock findById to return the version after save
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn($version);

        // Should clear location_point when no geo data
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('location_point = NULL'),
                $this->callback(function ($params) {
                    return is_array($params) && count($params) === 1 && $params[0] === 123;
                })
            )
            ->willReturn(1);

        $result = $this->repository->save($version);

        $this->assertSame($version, $result);
    }

    public function test_save_handles_find_by_id_returns_null(): void
    {
        $version = $this->createVersion();
        $version->id = 123;

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($version);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // findById returns null, so save() should return the original version
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn(null);

        // No geo data, so should clear location_point
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('location_point = NULL'),
                $this->equalTo(array(123))
            )
            ->willReturn(1);

        $result = $this->repository->save($version);

        // Should return original version when findById returns null
        $this->assertSame($version, $result);
    }

    public function test_save_handles_slugs_sync(): void
    {
        $version = $this->createVersion();
        $version->id = 123;
        $version->slugs = new SlugPair(business: 'test-business', location: 'test-location');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($version);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn($version);

        // No geo data
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        // Should sync slugs to individual columns
        $result = $this->repository->save($version);

        $this->assertSame($version, $result);
        // Verify slugs were synced to columns
        $this->assertEquals('test-business', $result->businessSlug);
        $this->assertEquals('test-location', $result->locationSlug);
    }

    // ===== findByMinisiteId() ERROR PATHS =====

    public function test_find_by_minisite_id_throws_exception_on_query_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();

        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();

        $queryBuilder
            ->method('setFirstResult')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willThrowException(new \Exception('Query error'));

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->findByMinisiteId('test-site-id');
    }

    // ===== findLatestVersion() ERROR PATHS =====

    public function test_find_latest_version_throws_exception_on_query_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();

        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willThrowException(new \Exception('Query error'));

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->findLatestVersion('test-site-id');
    }

    // ===== findLatestDraft() ERROR PATHS =====

    public function test_find_latest_draft_throws_exception_on_query_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();

        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willThrowException(new \Exception('Query error'));

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->findLatestDraft('test-site-id');
    }

    // ===== getLatestDraftForEditing() ERROR PATHS =====

    public function test_get_latest_draft_for_editing_throws_runtime_exception_when_no_version_found(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();

        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No version found for minisite.');

        $partialMock->getLatestDraftForEditing('test-site-id');
    }

    // ===== findPublishedVersion() ERROR PATHS =====

    public function test_find_published_version_throws_exception_on_query_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willThrowException(new \Exception('Query error'));

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->findPublishedVersion('test-site-id');
    }

    // ===== getNextVersionNumber() ERROR PATHS =====

    public function test_get_next_version_number_throws_exception_on_query_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->method('where')
            ->willReturnSelf();

        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willThrowException(new \Exception('Query error'));

        // Use partial mock to override createQueryBuilder
        $partialMock = $this->getMockBuilder(VersionRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('createQueryBuilder'))
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->getNextVersionNumber('test-site-id');
    }

    // ===== delete() ERROR PATHS =====

    public function test_delete_returns_false_when_version_not_found(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 999)
            ->willReturn(null);

        $result = $this->repository->delete(999);

        $this->assertFalse($result);
    }

    public function test_delete_throws_exception_on_remove_error(): void
    {
        $version = $this->createVersion();
        $version->id = 123;

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn($version);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($version)
            ->willThrowException(new \Exception('Remove error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Remove error');

        $this->repository->delete(123);
    }

    // ===== loadLocationPoint() ERROR PATHS =====

    public function test_load_location_point_handles_exception_gracefully(): void
    {
        $version = $this->createVersion();
        $version->id = 123;

        // Mock find() to return version and trigger loadLocationPoint
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Version::class, 123)
            ->willReturn($version);

        // Connection throws exception when fetching location point
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willThrowException(new \Exception('Spatial function error'));

        // Should not throw - exception is caught and logged
        $result = $this->repository->find(123);

        $this->assertSame($version, $result);
        // geo should remain null when spatial functions fail
        $this->assertNull($result->geo);
    }

    // ===== HELPER METHODS =====

    private function createVersion(): Version
    {
        $version = new Version(
            id: null,
            minisiteId: 'test-site-id',
            versionNumber: 1,
            status: 'draft',
            label: 'Test Version',
            comment: 'Test comment',
            createdBy: 1,
            createdAt: new \DateTimeImmutable(),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: array('test' => 'data'),
            slugs: new SlugPair(business: 'test-business', location: 'test-location'),
            title: 'Test Title',
            name: 'Test Name',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null,
            siteTemplate: 'v2025',
            palette: 'default',
            industry: 'test',
            defaultLocale: 'en',
            schemaVersion: 1,
            siteVersion: 1,
            searchTerms: 'test'
        );

        return $version;
    }
}
