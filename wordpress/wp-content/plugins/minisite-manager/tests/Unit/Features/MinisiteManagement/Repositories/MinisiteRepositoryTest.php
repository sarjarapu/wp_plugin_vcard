<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteManagement\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Repositories\VersionRepository as VersionRepositoryContract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MinisiteRepository
 *
 * Tests error paths, method signatures, and basic logic.
 * Full database operations are tested in integration tests.
 */
#[CoversClass(MinisiteRepository::class)]
final class MinisiteRepositoryTest extends TestCase
{
    private MinisiteRepository $repository;
    private EntityManagerInterface|MockObject $entityManager;
    private Connection|MockObject $connection;
    private ClassMetadata|MockObject $classMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider (required by MinisiteRepository)
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        // Set required ClassMetadata properties
        $this->classMetadata->name = Minisite::class;

        $this->classMetadata
            ->method('getTableName')
            ->willReturn('wp_minisites');

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($this->classMetadata);

        $this->repository = new MinisiteRepository($this->entityManager, $this->classMetadata);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['minisite_version_repository']);
        parent::tearDown();
    }

    /**
     * Test that repository can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MinisiteRepository::class, $this->repository);
    }

    /**
     * Test findBySlugs method signature
     */
    public function test_find_by_slugs_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'findBySlugs');

        $this->assertEquals('findBySlugs', $reflection->getName());
        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('slugs', $params[0]->getName());
        $this->assertEquals(SlugPair::class, $params[0]->getType()->getName());
    }

    /**
     * Test findBySlugs throws exception on database error
     */
    public function test_find_by_slugs_throws_exception_on_database_error(): void
    {
        $slugs = new SlugPair('business', 'location');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->method('from')
            ->willReturnSelf();

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
            ->method('getOneOrNullResult')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->findBySlugs($slugs);
    }

    /**
     * Test findById method signature
     */
    public function test_find_by_id_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'findById');

        $this->assertEquals('findById', $reflection->getName());
        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    /**
     * Test findById throws exception on database error
     */
    public function test_find_by_id_throws_exception_on_database_error(): void
    {
        $this->entityManager
            ->method('find')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->findById('test-id');
    }

    /**
     * Test updateSlug method signature and documentation
     */
    public function test_update_slug_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateSlug');
        $this->assertEquals('updateSlug', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());
        $docComment = $reflection->getDocComment();
        $this->assertStringContainsString('Update the slug for a minisite', $docComment);
    }

    /**
     * Test updateSlugs method signature
     */
    public function test_update_slugs_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateSlugs');

        $this->assertEquals('updateSlugs', $reflection->getName());
        $this->assertEquals(3, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('businessSlug', $params[1]->getName());
        $this->assertEquals('locationSlug', $params[2]->getName());
    }

    /**
     * Test updateSlugs method signature and documentation
     */
    public function test_update_slugs_method_signature_and_docs(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateSlugs');
        $this->assertEquals('updateSlugs', $reflection->getName());
        $this->assertEquals(3, $reflection->getNumberOfParameters());
        $docComment = $reflection->getDocComment();
        $this->assertStringContainsString('Update business and location slugs', $docComment);
    }

    /**
     * Test updatePublishStatus method signature
     */
    public function test_update_publish_status_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updatePublishStatus');

        $this->assertEquals('updatePublishStatus', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('publishStatus', $params[1]->getName());
    }

    /**
     * Test updateCurrentVersionId method signature
     */
    public function test_update_current_version_id_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateCurrentVersionId');

        $this->assertEquals('updateCurrentVersionId', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('versionId', $params[1]->getName());
    }

    /**
     * Test updateCoordinates method signature
     */
    public function test_update_coordinates_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateCoordinates');

        $this->assertEquals('updateCoordinates', $reflection->getName());
        $this->assertEquals(4, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('lat', $params[1]->getName());
        $this->assertEquals('lng', $params[2]->getName());
        $this->assertEquals('updatedBy', $params[3]->getName());
    }

    /**
     * Test updateCoordinates returns early when coordinates are null
     */
    public function test_update_coordinates_returns_early_when_null(): void
    {
        // Should not throw or call database when lat/lng are null
        $this->repository->updateCoordinates('test-id', null, null, 1);

        $this->assertTrue(true); // If we get here, method executed without error
    }

    /**
     * Test updateCoordinates throws exception when no rows affected
     */
    public function test_update_coordinates_throws_exception_when_no_rows_affected(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $queryBuilder
            ->method('update')
            ->willReturnSelf();

        $queryBuilder
            ->method('set')
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
            ->method('execute')
            ->willReturn(0);

        $this->connection
            ->method('executeStatement')
            ->willReturn(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found or update failed');

        $this->repository->updateCoordinates('test-id', 40.7128, -74.0060, 1);
    }

    /**
     * Test updateCoordinates updates location_point when coordinates provided
     */
    public function test_update_coordinates_updates_location_point(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->method('from')
            ->willReturnSelf();

        $queryBuilder
            ->method('update')
            ->willReturnSelf();

        $queryBuilder
            ->method('set')
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
            ->method('execute')
            ->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('POINT'),
                $this->equalTo(array(-74.0060, 40.7128, 'test-id'))
            )
            ->willReturn(1);

        $this->repository->updateCoordinates('test-id', 40.7128, -74.0060, 99);
    }

    /**
     * Test updateTitle method signature
     */
    public function test_update_title_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateTitle');

        $this->assertEquals('updateTitle', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('minisiteId', $params[0]->getName());
        $this->assertEquals('title', $params[1]->getName());
    }

    /**
     * Test updateTitle method returns boolean
     */
    public function test_update_title_returns_boolean(): void
    {
        // updateTitle returns bool - true on success, false on error
        // Full database testing is done in integration tests
        $reflection = new \ReflectionMethod($this->repository, 'updateTitle');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * Test updateStatus method signature
     */
    public function test_update_status_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateStatus');

        $this->assertEquals('updateStatus', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('minisiteId', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
    }

    /**
     * Test updateStatus method returns boolean
     */
    public function test_update_status_returns_boolean(): void
    {
        // updateStatus returns bool - true on success, false on error
        // Full database testing is done in integration tests
        $reflection = new \ReflectionMethod($this->repository, 'updateStatus');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * Test updateBusinessInfo method signature and exception documentation
     */
    public function test_update_business_info_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'updateBusinessInfo');

        $this->assertEquals('updateBusinessInfo', $reflection->getName());
        $this->assertEquals(3, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('minisiteId', $params[0]->getName());
        $this->assertEquals('fields', $params[1]->getName());
        $this->assertEquals('updatedBy', $params[2]->getName());

        // Verify it can throw RuntimeException
        $sourceCode = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('Failed to update business info fields', $sourceCode);
    }

    /**
     * Test updateMinisiteFields updates location_point via raw SQL when provided
     */
    public function test_update_minisite_fields_updates_location_point(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->method('execute')
            ->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('location_point = POINT'),
                $this->equalTo(array('minisite-123'))
            )
            ->willReturn(1);

        $fields = array(
            'title' => 'Updated Title',
            'location_point' => 'POINT(1, 2)',
        );

        $this->repository->updateMinisiteFields('minisite-123', $fields, 42);
    }

    /**
     * Test updateMinisiteFields throws when no rows updated and location_point not provided
     */
    public function test_update_minisite_fields_throws_when_no_rows_updated(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->method('execute')
            ->willReturn(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to update minisite fields.');

        $this->repository->updateMinisiteFields('minisite-456', array('title' => 'Unused'), 7);
    }

    /**
     * Test publishMinisite throws when version repository missing
     */
    public function test_publish_minisite_throws_when_version_repository_missing(): void
    {
        unset($GLOBALS['minisite_version_repository']);

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        $repository = $this->repository;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VersionRepository not initialized');

        $repository->publishMinisite('site-123');
    }

    /**
     * Test publishMinisite promotes the latest draft version
     */
    public function test_publish_minisite_promotes_latest_draft_version(): void
    {
        $versionRepo = $this->createMock(VersionRepositoryContract::class);
        $GLOBALS['minisite_version_repository'] = $versionRepo;

        $version = new Version(
            id: 101,
            minisiteId: 'site-123',
            versionNumber: 2,
            status: 'draft',
            siteJson: array('title' => 'Draft Title'),
            slugs: new SlugPair('biz', 'loc'),
            title: 'Draft Title',
            name: 'Draft Name',
            city: 'Draft City',
            region: 'CA',
            countryCode: 'US',
            postalCode: '94016',
            geo: new GeoPoint(37.0, -122.0),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 2,
            siteVersion: 5,
            searchTerms: 'draft search terms'
        );

        $minisite = new Minisite(
            id: 'site-123',
            name: 'Original Name',
            city: 'Original City'
        );

        $repository = $this->getMockBuilder(MinisiteRepository::class)
            ->setConstructorArgs(array($this->entityManager, $this->classMetadata))
            ->onlyMethods(array('findById'))
            ->getMock();

        $repository
            ->method('findById')
            ->with('site-123')
            ->willReturn($minisite);

        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('commit');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($minisite);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $versionRepo
            ->expects($this->once())
            ->method('findLatestDraft')
            ->with('site-123')
            ->willReturn($version);

        $versionRepo
            ->expects($this->never())
            ->method('findLatestVersion');

        $versionRepo
            ->expects($this->once())
            ->method('findPublishedVersion')
            ->with('site-123')
            ->willReturn(null);

        $versionRepo
            ->expects($this->once())
            ->method('save')
            ->with($version);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('POINT'),
                $this->equalTo(array(-122.0, 37.0, 'site-123'))
            )
            ->willReturn(1);

        $repository->publishMinisite('site-123');

        $this->assertEquals('Draft Title', $minisite->title);
        $this->assertEquals('published', $minisite->status);
        $this->assertEquals('site-123', $version->minisiteId);
        $this->assertEquals($version->id, $minisite->currentVersionId);
    }

    /**
     * Test listByOwner method signature
     */
    public function test_list_by_owner_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'listByOwner');

        $this->assertEquals('listByOwner', $reflection->getName());
        $this->assertEquals(3, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertEquals('offset', $params[2]->getName());
    }

    /**
     * Test listByOwner throws exception on database error
     */
    public function test_list_by_owner_throws_exception_on_database_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->method('from')
            ->willReturnSelf();

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
            ->method('addOrderBy')
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
            ->method('getResult')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->listByOwner(1, 50, 0);
    }

    /**
     * Test countByOwner method signature
     */
    public function test_count_by_owner_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'countByOwner');

        $this->assertEquals('countByOwner', $reflection->getName());
        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
    }

    /**
     * Test countByOwner throws exception on database error
     */
    public function test_count_by_owner_throws_exception_on_database_error(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->method('select')
            ->willReturnSelf();

        $queryBuilder
            ->method('from')
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
            ->method('getSingleScalarResult')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->countByOwner(1);
    }

    /**
     * Test loadLocationPoint sets GeoPoint when coordinates exist
     */
    public function test_load_location_point_sets_geo(): void
    {
        $minisite = new Minisite(
            id: 'geo-test',
            name: 'Geo Test',
            city: 'Geo City'
        );

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('SELECT ST_X'), array('geo-test'))
            ->willReturn(array('lat' => '40.7128', 'lng' => '-74.0060'));

        $reflection = new \ReflectionMethod($this->repository, 'loadLocationPoint');
        $reflection->setAccessible(true);
        $reflection->invoke($this->repository, $minisite);

        $this->assertNotNull($minisite->geo);
        $this->assertEquals(40.7128, $minisite->geo->getLat());
        $this->assertEquals(-74.0060, $minisite->geo->getLng());
    }

    /**
     * Test loadLocationPoint handles missing coordinates
     */
    public function test_load_location_point_handles_missing_coordinates(): void
    {
        $minisite = new Minisite(
            id: 'geo-missing',
            name: 'Geo Missing',
            city: 'Geo City'
        );

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('SELECT ST_X'), array('geo-missing'))
            ->willReturn(false);

        $reflection = new \ReflectionMethod($this->repository, 'loadLocationPoint');
        $reflection->setAccessible(true);
        $reflection->invoke($this->repository, $minisite);

        $this->assertNull($minisite->geo);
    }

    /**
     * Test populateSlugs sets SlugPair when columns exist
     */
    public function test_populate_slugs_sets_slug_pair(): void
    {
        $minisite = new Minisite(
            id: 'slug-test',
            name: 'Slug Test',
            city: 'Slug City'
        );
        $minisite->businessSlug = 'biz';
        $minisite->locationSlug = 'loc';

        $reflection = new \ReflectionMethod($this->repository, 'populateSlugs');
        $reflection->setAccessible(true);
        $reflection->invoke($this->repository, $minisite);

        $this->assertNotNull($minisite->slugs);
        $this->assertEquals('biz', $minisite->slugs->business);
        $this->assertEquals('loc', $minisite->slugs->location);
    }

    /**
     * Test populateSlugs leaves slugs null when columns missing
     */
    public function test_populate_slugs_handles_missing_columns(): void
    {
        $minisite = new Minisite(
            id: 'slug-missing',
            name: 'Slug Missing',
            city: 'Slug City'
        );

        $reflection = new \ReflectionMethod($this->repository, 'populateSlugs');
        $reflection->setAccessible(true);
        $reflection->invoke($this->repository, $minisite);

        $this->assertNull($minisite->slugs);
    }

    /**
     * Test save method signature and exception documentation
     */
    public function test_save_method_signature_and_exception_documentation(): void
    {
        $reflection = new \ReflectionMethod($this->repository, 'save');

        $this->assertEquals('save', $reflection->getName());
        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('m', $params[0]->getName());
        $this->assertEquals('expectedSiteVersion', $params[1]->getName());

        // Verify exception documentation
        $docComment = $reflection->getDocComment();
        $this->assertStringContainsString('@throws', $docComment);
        $this->assertStringContainsString('RuntimeException', $docComment);

        // Verify exception messages in source code
        $sourceCode = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('Concurrent modification detected', $sourceCode);
        $this->assertStringContainsString('Failed to reload minisite after save', $sourceCode);
    }

}
