<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineFactory::class)]
final class DoctrineFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_register_custom_types_registers_enum_mapping(): void
    {
        $platform = \Mockery::mock(AbstractPlatform::class);
        $platform->shouldReceive('hasDoctrineTypeMappingFor')->with('enum')->andReturn(false);
        $platform->shouldReceive('registerDoctrineTypeMapping')->once()->with('enum', 'string');
        $platform->shouldReceive('hasDoctrineTypeMappingFor')->with('point')->andReturn(true);

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);

        $typeMock = \Mockery::mock('alias:Doctrine\DBAL\Types\Type');
        $typeMock->shouldReceive('hasType')->with('point')->andReturn(true);

        DoctrineFactory::registerCustomTypes($connection);
    }

    public function test_register_custom_types_registers_point_type_when_missing(): void
    {
        $platform = \Mockery::mock(AbstractPlatform::class);
        $platform->shouldReceive('hasDoctrineTypeMappingFor')->with('enum')->andReturn(true);
        $platform->shouldReceive('hasDoctrineTypeMappingFor')->with('point')->andReturn(false);
        $platform->shouldReceive('registerDoctrineTypeMapping')->once()->with('point', 'point');

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);

        $typeMock = \Mockery::mock('alias:Doctrine\DBAL\Types\Type');
        $typeMock->shouldReceive('hasType')->with('point')->andReturn(false);
        $typeMock->shouldReceive('addType')
            ->once()
            ->with('point', \Minisite\Infrastructure\Persistence\Doctrine\Types\PointType::class);

        DoctrineFactory::registerCustomTypes($connection);
    }

    public function test_create_entity_manager_registers_table_prefix_listener(): void
    {
        $platform = \Mockery::mock(AbstractPlatform::class);
        $platform->shouldReceive('hasDoctrineTypeMappingFor')->andReturn(true);
        $platform->shouldReceive('registerDoctrineTypeMapping')->never();

        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);

        $driverMock = \Mockery::mock('alias:Doctrine\DBAL\DriverManager');
        $driverMock->shouldReceive('getConnection')->andReturn($connection);

        $ormSetupMock = \Mockery::mock('alias:Doctrine\ORM\ORMSetup');
        $ormSetupMock->shouldReceive('createAttributeMetadataConfiguration')->andReturn('config');

        $eventManager = new class () {
            public array $events = array();
            public function addEventListener($events, $listener): void
            {
                $this->events[] = array((array) $events, $listener);
            }
        };

        $entityManagerMock = \Mockery::mock('overload:Doctrine\ORM\EntityManager');
        $entityManagerMock->shouldReceive('getEventManager')->andReturn($eventManager);

        $loggerMock = \Mockery::mock('alias:Minisite\Infrastructure\Logging\LoggingServiceProvider');
        $loggerMock->shouldReceive('getFeatureLogger')->andReturn(new \Psr\Log\NullLogger());

        $typeMock = \Mockery::mock('alias:Doctrine\DBAL\Types\Type');
        $typeMock->shouldReceive('hasType')->andReturn(true);

        $wpdb = new class () {
            public string $prefix = 'wp_';
        };
        $GLOBALS['wpdb'] = $wpdb;

        $result = DoctrineFactory::createEntityManager($wpdb);

        $this->assertSame($entityManagerMock, $result);
        $this->assertNotEmpty($eventManager->events);
        $listener = $eventManager->events[0][1];

        $reflection = new \ReflectionClass($listener);
        $property = $reflection->getProperty('prefix');
        $property->setAccessible(true);
        $this->assertSame('wp_', $property->getValue($listener));
    }
}
