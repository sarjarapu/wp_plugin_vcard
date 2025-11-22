<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\ActivationHandler;
use Psr\Log\AbstractLogger;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(ActivationHandler::class)]
final class ActivationHandlerTest extends CoreTestCase
{
    public function testHandleSetsFlushOptionRunsMigrationsAndQueuesSeeder(): void
    {
        $migrationRunner = new class {
            public int $migrateCalls = 0;

            public function migrate(): void
            {
                $this->migrateCalls++;
            }
        };

        $roleSyncCalls = 0;

        ActivationHandler::setMigrationRunnerFactory(static fn () => $migrationRunner);
        ActivationHandler::setRoleSyncCallback(static function () use (&$roleSyncCalls): void {
            $roleSyncCalls++;
        });

        Functions\expect('update_option')
            ->once()
            ->with('minisite_flush_rewrites', 1, false)
            ->andReturnTrue();

        Functions\expect('add_action')
            ->once()
            ->with('init', array(ActivationHandler::class, 'seedDefaultConfigs'), 15)
            ->andReturnNull();

        ActivationHandler::handle();

        $this->assertSame(1, $migrationRunner->migrateCalls, 'Migrations should run once');
        $this->assertSame(1, $roleSyncCalls, 'Role synchronization should run once');
    }

    public function testRunMigrationsSkipsWhenDoctrineUnavailableAndLogsWarning(): void
    {
        $logger = new ActivationHandlerLoggerSpy();
        ActivationHandler::setDoctrineAvailableOverride(false);
        ActivationHandler::setLoggerFactory(static fn () => $logger);
        ActivationHandler::setMigrationRunnerFactory(static function (): void {
            throw new \RuntimeException('Runner should not be created when Doctrine is missing');
        });

        $this->invokeRunMigrations();

        $this->assertTrue($logger->hasLevel('warning'));
        $this->assertFalse($logger->hasLevel('error'));
    }

    public function testRunMigrationsLogsErrorWhenRunnerThrows(): void
    {
        $logger = new ActivationHandlerLoggerSpy();
        ActivationHandler::setLoggerFactory(static fn () => $logger);
        ActivationHandler::setMigrationRunnerFactory(static function (): object {
            return new class {
                public function migrate(): void
                {
                    throw new \RuntimeException('boom');
                }
            };
        });

        $this->invokeRunMigrations();

        $this->assertTrue($logger->hasLevel('error'));
    }

    public function testSeedDefaultConfigsSeedsWhenManagerExists(): void
    {
        $configManager = new \stdClass();
        $GLOBALS['minisite_config_manager'] = $configManager;

        $seeder = new class {
            public int $seedCalls = 0;
            public array $received = array();

            public function seedDefaults($manager): void
            {
                $this->seedCalls++;
                $this->received[] = $manager;
            }
        };

        ActivationHandler::setConfigSeederFactory(static fn () => $seeder);

        ActivationHandler::seedDefaultConfigs();

        $this->assertSame(1, $seeder->seedCalls);
        $this->assertSame($configManager, $seeder->received[0]);
    }

    public function testSeedDefaultConfigsQueuesRetryWhenManagerMissing(): void
    {
        ActivationHandler::setDoctrineAvailableOverride(false);
        Functions\expect('add_action')
            ->once()
            ->with('init', array(ActivationHandler::class, 'seedDefaultConfigs'), 20)
            ->andReturnNull();

        ActivationHandler::seedDefaultConfigs();
    }

    public function testSeedDefaultConfigsLogsErrorWhenSeederFails(): void
    {
        $logger = new ActivationHandlerLoggerSpy();
        $GLOBALS['minisite_config_manager'] = new \stdClass();

        ActivationHandler::setLoggerFactory(static fn () => $logger);
        ActivationHandler::setConfigSeederFactory(static function (): object {
            return new class {
                public function seedDefaults(): void
                {
                    throw new \RuntimeException('seeding failed');
                }
            };
        });

        ActivationHandler::seedDefaultConfigs();

        $this->assertTrue($logger->hasLevel('error'));
    }

    /**
     * Call private runMigrations() helper via reflection.
     */
    private function invokeRunMigrations(): void
    {
        $reflection = new \ReflectionClass(ActivationHandler::class);
        $method = $reflection->getMethod('runMigrations');
        $method->setAccessible(true);
        $method->invoke(null);
    }
}

final class ActivationHandlerLoggerSpy extends AbstractLogger
{
    /** @var array<int, array{level:string,message:string,context:array}> */
    public array $records = array();

    public function log($level, $message, array $context = array()): void
    {
        $this->records[] = array(
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        );
    }

    public function hasLevel(string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return true;
            }
        }

        return false;
    }
}
