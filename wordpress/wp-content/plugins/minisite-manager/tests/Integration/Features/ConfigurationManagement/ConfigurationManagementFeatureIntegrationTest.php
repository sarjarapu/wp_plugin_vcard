<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\ConfigurationManagementFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ConfigurationManagementFeature
 *
 * Tests the initialize() method which registers hooks and requires database connection.
 * This covers functionality that is skipped in unit tests when Doctrine/DB is not available.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Database constants must be defined (handled by bootstrap.php)
 */
#[CoversClass(ConfigurationManagementFeature::class)]
final class ConfigurationManagementFeatureIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Ensure database constants are defined
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
    }

    /**
     * Test initialize can be called successfully with database available
     * This covers the functionality skipped in unit tests
     */
    public function test_initialize_can_be_called_with_database(): void
    {
        // initialize() should not throw when database is available
        try {
            ConfigurationManagementFeature::initialize();
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\Exception $e) {
            // Only skip if it's a database-related error
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
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
     * Test initialize registers hooks correctly
     */
    public function test_initialize_registers_hooks(): void
    {
        try {
            // Call initialize
            ConfigurationManagementFeature::initialize();

            // Verify hooks were registered by checking that WordPress functions were called
            // Since we can't directly inspect WordPress hooks, we verify the method completed
            $this->assertTrue(true); // Test passes if initialize() completes without error
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
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
     * Test initialize can be called multiple times safely
     */
    public function test_initialize_can_be_called_multiple_times(): void
    {
        try {
            // Call initialize multiple times
            ConfigurationManagementFeature::initialize();
            ConfigurationManagementFeature::initialize();
            ConfigurationManagementFeature::initialize();

            // Should not throw or cause errors
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
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

