<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooks;
use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurationManagementHooksFactory
 *
 * NOTE: This factory creates Doctrine EntityManager which requires database connection.
 * Full factory testing should be done in integration tests.
 * These unit tests verify method signatures and basic structure.
 */
#[CoversClass(ConfigurationManagementHooksFactory::class)]
final class ConfigurationManagementHooksFactoryTest extends TestCase
{
    /**
     * Test create method is static
     */
    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooksFactory::class);
        $createMethod = $reflection->getMethod('create');

        $this->assertTrue($createMethod->isStatic());
        $this->assertTrue($createMethod->isPublic());
    }

    /**
     * Test create method exists and is callable
     */
    public function test_create_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists(ConfigurationManagementHooksFactory::class, 'create'));
        $this->assertTrue(is_callable([ConfigurationManagementHooksFactory::class, 'create']));
    }

    /**
     * Test create method returns ConfigurationManagementHooks instance
     * NOTE: This will fail if Doctrine is not available or DB connection fails
     * This test is primarily for coverage - full testing is done in integration tests
     */
    public function test_create_returns_configuration_management_hooks_instance(): void
    {
        // Define DB constants if not already defined
        if (!defined('DB_HOST')) {
            define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
        }
        if (!defined('DB_USER')) {
            define('DB_USER', getenv('MYSQL_USER') ?: 'minisite');
        }
        if (!defined('DB_PASSWORD')) {
            define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'minisite');
        }
        if (!defined('DB_NAME')) {
            define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'minisite_test');
        }

        try {
            // Call create() to ensure code is executed for coverage
            $hooks = ConfigurationManagementHooksFactory::create();
            $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks);
        } catch (\Exception $e) {
            // If Doctrine is not available or DB connection fails, skip this test
            // But note: This means coverage won't be recorded for this test
            // Integration tests should provide coverage when DB is available
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE')) {
                $this->markTestSkipped('Doctrine not available or DB connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooksFactory::class);
        $this->assertTrue($reflection->isFinal());
    }

    /**
     * Test class has no constructor
     */
    public function test_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementHooksFactory::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor);
    }
}

