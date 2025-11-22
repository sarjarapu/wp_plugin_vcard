<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Contracts;

use Minisite\Infrastructure\Persistence\Contracts\TransactionManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionManagerInterface::class)]
final class TransactionManagerInterfaceTest extends TestCase
{
    public function test_interface_defines_expected_methods(): void
    {
        $reflection = new \ReflectionClass(TransactionManagerInterface::class);

        $this->assertTrue($reflection->isInterface());
        foreach (array('startTransaction', 'commitTransaction', 'rollbackTransaction') as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic());
            $this->assertSame(0, $method->getNumberOfRequiredParameters());
        }
    }
}
