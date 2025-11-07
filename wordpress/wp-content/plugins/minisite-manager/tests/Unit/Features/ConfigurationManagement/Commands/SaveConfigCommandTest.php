<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Commands;

use Minisite\Features\ConfigurationManagement\Commands\SaveConfigCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SaveConfigCommand
 */
#[CoversClass(SaveConfigCommand::class)]
final class SaveConfigCommandTest extends TestCase
{
    /**
     * Test constructor sets all properties
     */
    public function test_constructor_sets_all_properties(): void
    {
        $command = new SaveConfigCommand('test_key', 'test_value', 'string', 'Test description');

        $this->assertSame('test_key', $command->key);
        $this->assertSame('test_value', $command->value);
        $this->assertSame('string', $command->type);
        $this->assertSame('Test description', $command->description);
    }

    /**
     * Test constructor with null description
     */
    public function test_constructor_with_null_description(): void
    {
        $command = new SaveConfigCommand('test_key', 'test_value', 'string', null);

        $this->assertSame('test_key', $command->key);
        $this->assertSame('test_value', $command->value);
        $this->assertSame('string', $command->type);
        $this->assertNull($command->description);
    }

    /**
     * Test properties are readonly
     */
    public function test_properties_are_readonly(): void
    {
        $command = new SaveConfigCommand('test_key', 'test_value', 'string');

        $reflection = new \ReflectionClass($command);
        $keyProperty = $reflection->getProperty('key');

        $this->assertTrue($keyProperty->isReadOnly());
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(SaveConfigCommand::class);
        $this->assertTrue($reflection->isFinal());
    }
}

