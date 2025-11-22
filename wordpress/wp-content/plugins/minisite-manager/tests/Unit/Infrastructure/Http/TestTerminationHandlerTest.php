<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestTerminationHandler::class)]
final class TestTerminationHandlerTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $handler = new TestTerminationHandler();

        $this->assertInstanceOf(TerminationHandlerInterface::class, $handler);
    }

    public function test_terminate_is_no_op(): void
    {
        $handler = new TestTerminationHandler();

        // Should not throw or exit
        $handler->terminate();

        $this->addToAssertionCount(1);
    }
}
