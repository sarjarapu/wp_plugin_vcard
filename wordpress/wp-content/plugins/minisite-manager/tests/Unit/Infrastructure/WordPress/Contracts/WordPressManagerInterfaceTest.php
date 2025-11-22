<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\WordPress\Contracts;

use Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPressManagerInterface::class)]
final class WordPressManagerInterfaceTest extends TestCase
{
    public function test_interface_defines_expected_methods(): void
    {
        $reflection = new \ReflectionClass(WordPressManagerInterface::class);
        $this->assertTrue($reflection->isInterface());

        $expectedMethods = array(
            'getCurrentUser',
            'sanitizeTextField',
            'sanitizeTextareaField',
            'sanitizeUrl',
            'sanitizeEmail',
            'verifyNonce',
            'createNonce',
            'getHomeUrl',
        );

        foreach ($expectedMethods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Missing method {$method}");
            $this->assertTrue($reflection->getMethod($method)->isPublic());
        }
    }
}
