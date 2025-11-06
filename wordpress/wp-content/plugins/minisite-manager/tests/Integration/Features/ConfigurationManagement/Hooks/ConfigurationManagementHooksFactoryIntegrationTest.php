<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Hooks;

use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooks;
use Minisite\Features\ConfigurationManagement\Hooks\ConfigurationManagementHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ConfigurationManagementHooksFactory
 *
 * Tests the create() method which requires Doctrine EntityManager and database connection.
 * This covers functionality that is skipped in unit tests when Doctrine/DB is not available.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Database constants must be defined (handled by bootstrap.php)
 */
#[CoversClass(ConfigurationManagementHooksFactory::class)]
final class ConfigurationManagementHooksFactoryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Ensure database constants are defined
        if (! defined('DB_HOST')) {
            define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
        }
        if (! defined('DB_USER')) {
            define('DB_USER', getenv('MYSQL_USER') ?: 'minisite');
        }
        if (! defined('DB_PASSWORD')) {
            define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'minisite');
        }
        if (! defined('DB_NAME')) {
            define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'minisite_test');
        }
    }

    /**
     * Test create returns ConfigurationManagementHooks instance with database available
     * This covers the functionality skipped in unit tests
     */
    public function test_create_returns_hooks_instance_with_database(): void
    {
        try {
            // Call create() to ensure code is executed for coverage
            $hooks = ConfigurationManagementHooksFactory::create();
            $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks);
        } catch (\Exception $e) {
            // Only skip if it's a database-related error
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'Access denied') ||
                str_contains($errorMessage, '1045')) {
                $this->markTestSkipped('Database connection failed: ' . $errorMessage);
            } else {
                // Other errors should fail the test
                throw $e;
            }
        } catch (\Error $e) {
            // Handle fatal errors
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'Access denied') ||
                str_contains($errorMessage, '1045')) {
                $this->markTestSkipped('Database connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Test create returns same instance type on multiple calls
     */
    public function test_create_returns_consistent_instance(): void
    {
        try {
            $hooks1 = ConfigurationManagementHooksFactory::create();
            $hooks2 = ConfigurationManagementHooksFactory::create();

            $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks1);
            $this->assertInstanceOf(ConfigurationManagementHooks::class, $hooks2);
            // Note: They may be different instances, but should be same type
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'Access denied') ||
                str_contains($errorMessage, '1045')) {
                $this->markTestSkipped('Database connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        } catch (\Error $e) {
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'Access denied') ||
                str_contains($errorMessage, '1045')) {
                $this->markTestSkipped('Database connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }
}
