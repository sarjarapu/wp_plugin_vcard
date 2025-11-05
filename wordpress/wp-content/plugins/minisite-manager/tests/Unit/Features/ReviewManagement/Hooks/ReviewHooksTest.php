<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Hooks\ReviewHooks;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ReviewHooks
 */
#[CoversClass(ReviewHooks::class)]
final class ReviewHooksTest extends TestCase
{
    private ReviewRepository|MockObject $reviewRepository;
    private ReviewHooks $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->hooks = new ReviewHooks($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(ReviewHooks::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('Minisite\Features\ReviewManagement\Repositories\ReviewRepository', $params[0]->getType()->getName());
    }

    /**
     * Test register method exists and is callable
     */
    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->hooks, 'register'));
        $this->assertTrue(is_callable([$this->hooks, 'register']));
    }

    /**
     * Test register method can be called without errors
     * Currently register() is a placeholder/no-op
     */
    public function test_register_can_be_called(): void
    {
        // Currently register() is a no-op (placeholder for future functionality)
        // But it should not throw an exception
        $this->hooks->register();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test register method is public
     */
    public function test_register_is_public(): void
    {
        $reflection = new \ReflectionClass(ReviewHooks::class);
        $registerMethod = $reflection->getMethod('register');
        
        $this->assertTrue($registerMethod->isPublic());
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(ReviewHooks::class);
        $this->assertTrue($reflection->isFinal());
    }
}

