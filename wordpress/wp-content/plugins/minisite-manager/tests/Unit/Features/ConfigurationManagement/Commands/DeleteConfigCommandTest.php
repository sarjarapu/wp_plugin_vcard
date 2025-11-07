<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Commands;

use Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DeleteConfigCommand
 */
#[CoversClass(DeleteConfigCommand::class)]
final class DeleteConfigCommandTest extends TestCase
{
    /**
     * Test constructor sets key property
     */
    public function test_constructor_sets_key_property(): void
    {
        $command = new DeleteConfigCommand('test_key');

        $this->assertSame('test_key', $command->key);
    }

    /**
     * Test property is readonly
     */
    public function test_property_is_readonly(): void
    {
        $command = new DeleteConfigCommand('test_key');

        $reflection = new \ReflectionClass($command);
        $keyProperty = $reflection->getProperty('key');

        $this->assertTrue($keyProperty->isReadOnly());
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(DeleteConfigCommand::class);
        $this->assertTrue($reflection->isFinal());
    }
}

