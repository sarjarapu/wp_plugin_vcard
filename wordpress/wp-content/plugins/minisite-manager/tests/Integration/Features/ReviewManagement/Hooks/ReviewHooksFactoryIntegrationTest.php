<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Hooks\ReviewHooks;
use Minisite\Features\ReviewManagement\Hooks\ReviewHooksFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for ReviewHooksFactory
 * 
 * Tests ReviewHooksFactory creation with real Doctrine EntityManager.
 * Verifies that the factory correctly creates and configures ReviewHooks.
 * 
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - May be skipped if DB connection is unavailable
 */
#[CoversClass(ReviewHooksFactory::class)]
final class ReviewHooksFactoryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure WordPress constants are defined
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', '/tmp/wp-content');
        }
        
        // Define DB constants if not already defined
        $dbHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $dbPort = getenv('MYSQL_PORT') ?: '3307';
        
        if (!defined('DB_HOST')) {
            define('DB_HOST', $dbHost);
        }
        if (!defined('DB_PORT')) {
            define('DB_PORT', $dbPort);
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
        
        // Set up $wpdb for TablePrefixListener
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';
    }

    /**
     * Test create returns ReviewHooks instance
     */
    public function test_create_returns_review_hooks_instance(): void
    {
        try {
            $hooks = ReviewHooksFactory::create();
            $this->assertInstanceOf(ReviewHooks::class, $hooks);
        } catch (\Exception $e) {
            // If Doctrine is not available or DB connection fails, skip this test
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') || 
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'WP_CONTENT_DIR')) {
                $this->markTestSkipped('Doctrine not available or DB connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Test create injects correct dependencies
     */
    public function test_create_injects_correct_dependencies(): void
    {
        try {
            $hooks = ReviewHooksFactory::create();
            
            // Verify hooks has ReviewRepository injected
            $reflection = new \ReflectionClass($hooks);
            $repositoryProperty = $reflection->getProperty('reviewRepository');
            $repositoryProperty->setAccessible(true);
            $repository = $repositoryProperty->getValue($hooks);
            
            $this->assertNotNull($repository);
            $this->assertInstanceOf(
                \Minisite\Features\ReviewManagement\Repositories\ReviewRepository::class,
                $repository
            );
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') || 
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'WP_CONTENT_DIR')) {
                $this->markTestSkipped('Doctrine not available or DB connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }
}

