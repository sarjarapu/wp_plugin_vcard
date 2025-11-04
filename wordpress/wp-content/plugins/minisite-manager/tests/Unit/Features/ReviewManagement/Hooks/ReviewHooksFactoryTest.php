<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Hooks\ReviewHooks;
use Minisite\Features\ReviewManagement\Hooks\ReviewHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReviewHooksFactory
 * 
 * NOTE: This factory creates Doctrine EntityManager which requires database connection.
 * Full factory testing should be done in integration tests.
 * These unit tests verify method signatures and basic structure.
 */
#[CoversClass(ReviewHooksFactory::class)]
final class ReviewHooksFactoryTest extends TestCase
{
    /**
     * Test create method is static
     */
    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionClass(ReviewHooksFactory::class);
        $createMethod = $reflection->getMethod('create');
        
        $this->assertTrue($createMethod->isStatic());
        $this->assertTrue($createMethod->isPublic());
    }

    /**
     * Test create method exists and is callable
     */
    public function test_create_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists(ReviewHooksFactory::class, 'create'));
        $this->assertTrue(is_callable([ReviewHooksFactory::class, 'create']));
    }

    /**
     * Test create method returns ReviewHooks instance
     * NOTE: This will fail if Doctrine is not available or DB connection fails
     */
    public function test_create_returns_review_hooks_instance(): void
    {
        // Define DB constants if not already defined
        if (!defined('DB_HOST')) {
            define('DB_HOST', 'localhost');
        }
        if (!defined('DB_USER')) {
            define('DB_USER', 'test_user');
        }
        if (!defined('DB_PASSWORD')) {
            define('DB_PASSWORD', 'test_password');
        }
        if (!defined('DB_NAME')) {
            define('DB_NAME', 'test_database');
        }

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
        $reflection = new \ReflectionClass(ReviewHooksFactory::class);
        $this->assertTrue($reflection->isFinal());
    }

    /**
     * Test class has no constructor
     */
    public function test_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(ReviewHooksFactory::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNull($constructor);
    }
}

