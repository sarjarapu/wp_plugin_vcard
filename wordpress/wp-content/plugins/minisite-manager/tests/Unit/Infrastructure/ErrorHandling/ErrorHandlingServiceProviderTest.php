<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\ErrorHandling;

use Minisite\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorHandlingServiceProvider::class)]
final class ErrorHandlingServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        ErrorHandlingServiceProvider::unregister();
        \Mockery::close();
        parent::tearDown();
    }

    public function test_register_creates_and_registers_handler(): void
    {
        $mock = \Mockery::mock('overload:Minisite\Infrastructure\ErrorHandling\ErrorHandler');
        $mock->shouldReceive('register')
            ->once();

        ErrorHandlingServiceProvider::register();

        $this->assertNotNull(ErrorHandlingServiceProvider::getErrorHandler());
    }

    public function test_register_is_idempotent(): void
    {
        $mock = \Mockery::mock('overload:Minisite\Infrastructure\ErrorHandling\ErrorHandler');
        $mock->shouldReceive('register')->once();

        ErrorHandlingServiceProvider::register();
        ErrorHandlingServiceProvider::register();

        $this->assertNotNull(ErrorHandlingServiceProvider::getErrorHandler());
    }

    public function test_unregister_unregisters_handler(): void
    {
        $mock = \Mockery::mock('overload:Minisite\Infrastructure\ErrorHandling\ErrorHandler');
        $mock->shouldReceive('register')->once();
        $mock->shouldReceive('unregister')->once();

        ErrorHandlingServiceProvider::register();
        ErrorHandlingServiceProvider::unregister();

        $this->assertNull(ErrorHandlingServiceProvider::getErrorHandler());
    }

    public function test_get_error_handler_returns_handler(): void
    {
        $mock = \Mockery::mock('overload:Minisite\Infrastructure\ErrorHandling\ErrorHandler');
        $mock->shouldReceive('register')->once();

        ErrorHandlingServiceProvider::register();

        $this->assertSame($mock, ErrorHandlingServiceProvider::getErrorHandler());
    }

    public function test_get_error_handler_returns_null_when_not_registered(): void
    {
        $this->assertNull(ErrorHandlingServiceProvider::getErrorHandler());
    }
}
