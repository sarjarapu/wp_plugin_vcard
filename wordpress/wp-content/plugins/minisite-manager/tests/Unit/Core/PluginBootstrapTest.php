<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\ActivationHandler;
use Minisite\Core\DeactivationHandler;
use Minisite\Core\PluginBootstrap;
use Psr\Log\AbstractLogger;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(PluginBootstrap::class)]
final class PluginBootstrapTest extends CoreTestCase
{
    public function testInitializeRegistersLifecycleHooks(): void
    {
        $registered = array();

        Functions\when('register_activation_hook')->alias(function ($file, $callback) use (&$registered): void {
            $registered['activation'] = array($file, $callback);
        });

        Functions\when('register_deactivation_hook')->alias(function ($file, $callback) use (&$registered): void {
            $registered['deactivation'] = array($file, $callback);
        });

        $initHooks = array();
        Functions\when('add_action')->alias(function ($hook, $callback, $priority) use (&$initHooks): void {
            $initHooks[] = array($hook, $callback, $priority);
        });

        PluginBootstrap::initialize();

        $this->assertSame(array(MINISITE_PLUGIN_FILE, array(PluginBootstrap::class, 'onActivation')), $registered['activation']);
        $this->assertSame(array(MINISITE_PLUGIN_FILE, array(PluginBootstrap::class, 'onDeactivation')), $registered['deactivation']);

        $this->assertContains(array('init', array(PluginBootstrap::class, 'initializeCore'), 5), $initHooks);
        $this->assertContains(array('init', array(PluginBootstrap::class, 'initializeFeatures'), 10), $initHooks);
    }

    public function testOnActivationDelegatesToActivationHandler(): void
    {
        $roleSyncCalls = 0;
        ActivationHandler::setMigrationRunnerFactory(static fn () => new class {
            public function migrate(): void
            {
            }
        });
        ActivationHandler::setRoleSyncCallback(static function () use (&$roleSyncCalls): void {
            $roleSyncCalls++;
        });

        Functions\expect('update_option')->once()->andReturnTrue();
        Functions\expect('add_action')->once()->andReturnNull();

        PluginBootstrap::onActivation();

        $this->assertSame(1, $roleSyncCalls);
    }

    public function testOnDeactivationDelegatesToHandler(): void
    {
        DeactivationHandler::setProductionOverride(false);
        Functions\expect('flush_rewrite_rules')->once()->andReturnNull();

        PluginBootstrap::onDeactivation();
    }

    public function testInitializeConfigSystemLogsWarningWhenDoctrineMissing(): void
    {
        $logger = new LoggerSpy();
        PluginBootstrap::setDoctrineAvailableOverride(false);
        PluginBootstrap::setLoggerFactory(static fn () => $logger);

        PluginBootstrap::initializeConfigSystem();

        $this->assertTrue($logger->hasLevel('warning'));
    }

    public function testInitializeConfigSystemReusesExistingEntityManager(): void
    {
        $entityManager = new EntityManagerStub();
        $GLOBALS['minisite_entity_manager'] = $entityManager;

        PluginBootstrap::setDoctrineAvailableOverride(true);
        PluginBootstrap::setEntityManagerFactory(static function (): void {
            throw new \RuntimeException('Factory should not be called when EM is open');
        });
        PluginBootstrap::setRepositoryFactory(static fn ($class, $em, $entityClass) => "{$class}|{$entityClass}");

        PluginBootstrap::initializeConfigSystem();

        $this->assertSame($entityManager, $GLOBALS['minisite_entity_manager']);
        $this->assertSame(
            \Minisite\Features\ReviewManagement\Repositories\ReviewRepository::class . '|' .
            \Minisite\Features\ReviewManagement\Domain\Entities\Review::class,
            $GLOBALS['minisite_review_repository']
        );
        $this->assertSame(
            \Minisite\Features\VersionManagement\Repositories\VersionRepository::class . '|' .
            \Minisite\Features\VersionManagement\Domain\Entities\Version::class,
            $GLOBALS['minisite_version_repository']
        );
        $this->assertSame(
            \Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository::class . '|' .
            \Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class,
            $GLOBALS['minisite_repository']
        );
    }

    public function testInitializeConfigSystemLogsErrorOnException(): void
    {
        $logger = new LoggerSpy();
        PluginBootstrap::setLoggerFactory(static fn () => $logger);
        PluginBootstrap::setDoctrineAvailableOverride(true);
        PluginBootstrap::setEntityManagerFactory(static function (): void {
            throw new \RuntimeException('failed');
        });

        PluginBootstrap::initializeConfigSystem();

        $this->assertTrue($logger->hasLevel('error'));
    }
}

final class EntityManagerStub
{
    public function getConnection(): void
    {
        // Intentionally empty - just needs to not throw.
    }

    public function getClassMetadata(string $entity): \Doctrine\ORM\Mapping\ClassMetadata
    {
        return new \Doctrine\ORM\Mapping\ClassMetadata($entity);
    }
}

final class LoggerSpy extends AbstractLogger
{
    /** @var array<int, string> */
    private array $levels = array();

    public function log($level, $message, array $context = array()): void
    {
        $this->levels[] = (string) $level;
    }

    public function hasLevel(string $level): bool
    {
        return in_array($level, $this->levels, true);
    }
}
