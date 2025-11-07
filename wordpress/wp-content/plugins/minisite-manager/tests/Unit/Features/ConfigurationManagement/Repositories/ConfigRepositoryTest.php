<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Repositories;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ClassMetadata|MockObject $classMetadata;
    private ConfigRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        // Set required ClassMetadata properties that EntityRepository expects
        $this->classMetadata->name = Config::class;

        $this->repository = new ConfigRepository($this->entityManager, $this->classMetadata);
    }

    public function testSavePersistsAndFlushesConfig(): void
    {
        $config = $this->createTestConfig('test_key', 'test_value', 'string');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($config);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->repository->save($config);

        $this->assertSame($config, $result);
    }

    /**
     * Test save() handles exceptions and logs errors
     */
    public function testSaveHandlesExceptions(): void
    {
        $config = $this->createTestConfig('test_key', 'test_value', 'string');
        $exception = new \Exception('Database error');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($config);

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->repository->save($config);
    }

    public function testFindByKeyReturnsConfigWhenExists(): void
    {
        $config = $this->createTestConfig('test_key', 'test_value', 'string');
        $config->id = 1;

        // Mock createQueryBuilder chain for findOneBy
        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['key' => 'test_key'])
            ->willReturn($config);

        $result = $partialMock->findByKey('test_key');

        $this->assertNotNull($result);
        $this->assertEquals('test_key', $result->key);
    }

    public function testFindByKeyReturnsNullWhenNotExists(): void
    {
        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['key' => 'non_existent'])
            ->willReturn(null);

        $result = $partialMock->findByKey('non_existent');

        $this->assertNull($result);
    }

    /**
     * Test findByKey() handles exceptions
     */
    public function testFindByKeyHandlesExceptions(): void
    {
        $exception = new \Exception('Query error');

        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->findByKey('test_key');
    }

    public function testGetAllReturnsAllConfigsOrderedByKey(): void
    {
        $config1 = $this->createTestConfig('alpha', 'value1', 'string');
        $config2 = $this->createTestConfig('zebra', 'value2', 'string');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$config1, $config2]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $result = $partialMock->getAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Config::class, $result[0]);
        $this->assertInstanceOf(Config::class, $result[1]);
    }

    /**
     * Test getAll() handles exceptions
     */
    public function testGetAllHandlesExceptions(): void
    {
        $exception = new \Exception('Query error');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willThrowException($exception);

        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $partialMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query error');

        $partialMock->getAll();
    }

    public function testDeleteRemovesConfig(): void
    {
        $config = $this->createTestConfig('to_delete', 'value', 'string');
        $config->id = 1;

        // Mock findByKey to return config
        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findByKey'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findByKey')
            ->with('to_delete')
            ->willReturn($config);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($config);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $partialMock->delete('to_delete');
    }

    public function testDeleteDoesNothingWhenConfigNotFound(): void
    {
        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findByKey'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findByKey')
            ->with('non_existent')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $partialMock->delete('non_existent');
    }

    /**
     * Test delete() handles exceptions
     */
    public function testDeleteHandlesExceptions(): void
    {
        $config = $this->createTestConfig('to_delete', 'value', 'string');
        $exception = new \Exception('Database error');

        $partialMock = $this->getMockBuilder(ConfigRepository::class)
            ->setConstructorArgs([$this->entityManager, $this->classMetadata])
            ->onlyMethods(['findByKey'])
            ->getMock();

        $partialMock
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn($config);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($config);

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willThrowException($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $partialMock->delete('to_delete');
    }

    /**
     * Helper to create a Config entity for testing
     */
    private function createTestConfig(string $key, string $value, string $type): Config
    {
        $config = new Config();
        $config->key = $key;
        $config->setTypedValue($value);
        $config->type = $type;
        return $config;
    }
}

