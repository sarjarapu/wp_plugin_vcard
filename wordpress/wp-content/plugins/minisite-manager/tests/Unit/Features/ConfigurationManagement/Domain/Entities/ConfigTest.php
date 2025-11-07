<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Domain\Entities;

use DateTimeImmutable;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testConstructorSetsDefaultValues(): void
    {
        $config = new Config();

        $this->assertNull($config->id);
        $this->assertInstanceOf(DateTimeImmutable::class, $config->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $config->updatedAt);
        $this->assertSame('string', $config->type);
        $this->assertFalse($config->isSensitive);
        $this->assertFalse($config->isRequired);
    }

    public function testCanSetAllFields(): void
    {
        $now = new DateTimeImmutable('2025-01-01T00:00:00Z');

        $config = new Config();
        $config->id = 1;
        $config->key = 'test_key';
        $config->value = 'test_value';
        $config->type = 'string';
        $config->description = 'Test description';
        $config->isSensitive = true;
        $config->isRequired = true;
        $config->createdAt = $now;
        $config->updatedAt = $now;

        $this->assertSame(1, $config->id);
        $this->assertSame('test_key', $config->key);
        $this->assertSame('test_value', $config->value);
        $this->assertSame('string', $config->type);
        $this->assertSame('Test description', $config->description);
        $this->assertTrue($config->isSensitive);
        $this->assertTrue($config->isRequired);
        $this->assertSame($now, $config->createdAt);
        $this->assertSame($now, $config->updatedAt);
    }

    public function testGetTypedValueReturnsStringByDefault(): void
    {
        $config = new Config();
        $config->value = 'test_value';
        $config->type = 'string';

        $result = $config->getTypedValue();

        $this->assertSame('test_value', $result);
    }

    public function testGetTypedValueConvertsInteger(): void
    {
        $config = new Config();
        $config->value = '42';
        $config->type = 'integer';

        $result = $config->getTypedValue();

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function testGetTypedValueConvertsBoolean(): void
    {
        $config = new Config();
        $config->value = '1';
        $config->type = 'boolean';

        $result = $config->getTypedValue();

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetTypedValueConvertsJson(): void
    {
        $jsonData = ['key' => 'value', 'number' => 123];
        $config = new Config();
        $config->value = json_encode($jsonData);
        $config->type = 'json';

        $result = $config->getTypedValue();

        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }

    public function testGetTypedValueDecryptsEncrypted(): void
    {
        // Define encryption key for testing
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $originalValue = 'secret_value_12345';
        $config = new Config();
        $config->type = 'encrypted';
        $config->setTypedValue($originalValue);

        // Verify value is encrypted
        $this->assertNotEquals($originalValue, $config->value);
        $this->assertNotEmpty($config->value);

        // Verify we can decrypt it
        $result = $config->getTypedValue();
        $this->assertEquals($originalValue, $result);
    }

    public function testGetTypedValueReturnsSecretAsIs(): void
    {
        $config = new Config();
        $config->value = 'hashed_secret_value';
        $config->type = 'secret';

        $result = $config->getTypedValue();

        // Secret type should return value as-is (never decrypt)
        $this->assertSame('hashed_secret_value', $result);
    }

    public function testGetTypedValueReturnsNullWhenValueNull(): void
    {
        $config = new Config();
        $config->value = null;
        $config->type = 'encrypted';

        $result = $config->getTypedValue();

        $this->assertNull($result);
    }

    public function testSetTypedValueConvertsInteger(): void
    {
        $config = new Config();
        $config->type = 'integer';
        $config->setTypedValue(42);

        $this->assertSame('42', $config->value);
        $this->assertInstanceOf(DateTimeImmutable::class, $config->updatedAt);
    }

    public function testSetTypedValueConvertsBoolean(): void
    {
        $config = new Config();
        $config->type = 'boolean';
        $config->setTypedValue(true);

        $this->assertSame('1', $config->value);

        $config->setTypedValue(false);
        $this->assertSame('0', $config->value);
    }

    public function testSetTypedValueConvertsJson(): void
    {
        $jsonData = ['key' => 'value'];
        $config = new Config();
        $config->type = 'json';
        $config->setTypedValue($jsonData);

        $this->assertSame(json_encode($jsonData), $config->value);
    }

    public function testSetTypedValueEncryptsEncrypted(): void
    {
        // Define encryption key for testing
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $originalValue = 'secret_value_12345';
        $config = new Config();
        $config->type = 'encrypted';
        $config->setTypedValue($originalValue);

        // Verify value is encrypted
        $this->assertNotEquals($originalValue, $config->value);
        $this->assertNotEmpty($config->value);

        // Verify we can decrypt it back
        $decrypted = $config->getTypedValue();
        $this->assertEquals($originalValue, $decrypted);
    }

    public function testSetTypedValueHashesSecret(): void
    {
        $originalValue = 'secret_value_12345';
        $config = new Config();
        $config->type = 'secret';
        $config->setTypedValue($originalValue);

        // Secret type should hash the value
        $this->assertNotEquals($originalValue, $config->value);
        $this->assertEquals(64, strlen($config->value)); // SHA256 hash length
        $this->assertEquals(hash('sha256', $originalValue), $config->value);
    }

    public function testSetTypedValueUpdatesTimestamp(): void
    {
        $config = new Config();
        $originalUpdatedAt = $config->updatedAt;

        usleep(1000); // Small delay
        $config->setTypedValue('new_value');

        $this->assertGreaterThan($originalUpdatedAt, $config->updatedAt);
    }

    public function testOptionalFieldsCanBeNull(): void
    {
        $config = new Config();
        $config->key = 'test_key';
        $config->type = 'string';

        $this->assertNull($config->description);
        $this->assertNull($config->value);
    }
}

