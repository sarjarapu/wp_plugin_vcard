<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Connection;
use Minisite\Infrastructure\Migrations\Doctrine\BaseDoctrineMigration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestLogger;

#[CoversClass(BaseDoctrineMigration::class)]
final class BaseDoctrineMigrationTest extends TestCase
{
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new TestLogger();
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Logging\LoggingServiceProvider');
        $mock->shouldReceive('getFeatureLogger')->andReturn($this->logger);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        unset(
            $GLOBALS['minisite_entity_manager'],
            $GLOBALS['minisite_repository'],
            $GLOBALS['minisite_version_repository'],
            $GLOBALS['minisite_review_repository']
        );
        parent::tearDown();
    }

    public function test_constructor_sets_logger(): void
    {
        $migration = $this->createMigration();
        $reflection = new \ReflectionClass($migration);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);

        $this->assertSame($this->logger, $property->getValue($migration));
    }

    public function test_add_foreign_key_if_not_exists_adds_when_missing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $migration = $this->createMigration($connection);
        $migration->addForeignKeyIfNotExists(
            'wp_table',
            'fk_test',
            'column_id',
            'wp_other',
            'id'
        );

        $this->assertNotEmpty($migration->sql);
        $this->assertStringContainsString('ALTER TABLE', $migration->sql[0]);
    }

    public function test_add_foreign_key_if_not_exists_skips_when_exists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $migration = $this->createMigration($connection);
        $migration->addForeignKeyIfNotExists('wp_table', 'fk_test', 'column', 'other', 'id');

        $this->assertSame(array(), $migration->sql);
        $this->assertTrue($this->logger->hasRecord('up() - foreign key already exists, skipping', 'info'));
    }

    public function test_is_transactional_returns_false(): void
    {
        $migration = $this->createMigration();
        $this->assertFalse($migration->isTransactional());
    }

    public function test_seed_sample_data_is_no_op(): void
    {
        $migration = $this->createMigration();
        $migration->seedSampleData();

        $this->addToAssertionCount(1);
    }

    public function test_should_seed_sample_data_returns_true(): void
    {
        $migration = $this->createMigration();
        $this->assertTrue($migration->shouldSeedSampleData());
    }

    public function test_ensure_repositories_initialized_clears_open_entity_manager(): void
    {
        $entityManager = new class () {
            public bool $cleared = false;

            public function getConnection(): void
            {
                // No-op
            }

            public function clear(): void
            {
                $this->cleared = true;
            }
        };

        $GLOBALS['minisite_entity_manager'] = $entityManager;
        $GLOBALS['minisite_repository'] = true;
        $GLOBALS['minisite_version_repository'] = true;
        $GLOBALS['minisite_review_repository'] = true;

        $migration = $this->createMigration();
        $this->invokeEnsureRepositoriesInitialized($migration);

        $this->assertTrue($entityManager->cleared);
    }

    public function test_ensure_repositories_initialized_handles_closed_entity_manager(): void
    {
        $entityManager = new class () {
            public bool $cleared = false;

            public function getConnection(): void
            {
                throw new \Doctrine\ORM\Exception\EntityManagerClosed('closed');
            }

            public function clear(): void
            {
                $this->cleared = true;
            }
        };

        $GLOBALS['minisite_entity_manager'] = $entityManager;

        $bootstrapMock = \Mockery::mock('alias:Minisite\Core\PluginBootstrap');
        $bootstrapMock->shouldReceive('initializeConfigSystem')->once();

        $migration = $this->createMigration();
        $this->invokeEnsureRepositoriesInitialized($migration);

        $this->assertFalse(isset($GLOBALS['minisite_entity_manager']));
    }

    /**
     * @return BaseDoctrineMigrationStub
     */
    private function createMigration(?Connection $connection = null)
    {
        $connection ??= $this->createMock(Connection::class);

        return new BaseDoctrineMigrationStub($connection, $this->logger);
    }

    private function invokeEnsureRepositoriesInitialized(BaseDoctrineMigrationStub $migration): void
    {
        $reflection = new \ReflectionClass(BaseDoctrineMigration::class);
        $method = $reflection->getMethod('ensureRepositoriesInitialized');
        $method->setAccessible(true);
        $method->invoke($migration);
    }
}

final class BaseDoctrineMigrationStub extends BaseDoctrineMigration
{
    /**
     * @var array<int, string>
     */
    public array $sql = array();

    public function addSql($sql, array $params = array(), array $types = array()): void
    {
        $this->sql[] = $sql;
    }
}
