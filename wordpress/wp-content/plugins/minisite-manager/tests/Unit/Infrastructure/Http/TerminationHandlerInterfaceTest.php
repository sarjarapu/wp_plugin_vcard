<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TerminationHandlerInterface::class)]
final class TerminationHandlerInterfaceTest extends TestCase
{
    public function test_interface_defines_terminate_method(): void
    {
        $reflection = new \ReflectionClass(TerminationHandlerInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('terminate'));
        $method = $reflection->getMethod('terminate');
        $this->assertTrue($method->isPublic());
        $this->assertSame(0, $method->getNumberOfRequiredParameters());
    }
}
