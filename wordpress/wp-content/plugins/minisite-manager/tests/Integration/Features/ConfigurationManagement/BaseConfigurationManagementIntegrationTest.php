<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement;

use PHPUnit\Framework\TestCase;

/**
 * Base class for ConfigurationManagement integration tests
 *
 * Provides common setup for tests that use DoctrineFactory to create EntityManager.
 * This is for tests that don't need direct EntityManager access but rely on
 * DoctrineFactory::createEntityManager() to work correctly.
 *
 * For tests that need direct EntityManager access, extend TestCase directly
 * and create EntityManager manually (see ConfigurationManagementServiceIntegrationTest).
 *
 * Note: PHPUnit will show a warning about this being abstract - this is expected
 * and harmless. Abstract base test classes are not executed as tests themselves.
 */
#[PHPUnit\Framework\Attributes\ExcludeFromClassCodeCoverage]
abstract class BaseConfigurationManagementIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Ensure database constants are defined (required by DoctrineFactory)
        if (! defined('DB_HOST')) {
            define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
        }
        if (! defined('DB_PORT')) {
            define('DB_PORT', getenv('MYSQL_PORT') ?: '3307');
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

        // Ensure $wpdb is set (required by TablePrefixListener)
        if (! isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';
    }
}
