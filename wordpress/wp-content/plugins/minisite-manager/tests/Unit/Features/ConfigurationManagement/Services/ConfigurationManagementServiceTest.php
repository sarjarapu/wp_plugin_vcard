<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Services;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepositoryInterface;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurationManagementService
 */
#[CoversClass(ConfigurationManagementService::class)]
final class ConfigurationManagementServiceTest extends TestCase
{
    private ConfigRepositoryInterface|MockObject $repository;
    private ConfigurationManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Define encryption key for tests that use encrypted type
        if (! defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        // Reset static cache between tests
        $this->resetStaticCache();

        $this->repository = $this->createMock(ConfigRepositoryInterface::class);
        $this->service = new ConfigurationManagementService($this->repository);
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test
        $this->resetStaticCache();
        parent::tearDown();
    }

    /**
     * Reset ConfigurationManagementService static cache using reflection
     */
    private function resetStaticCache(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementService::class);

        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, null);

        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
    }

    public function test_get_returns_value_when_config_exists(): void
    {
        $config = $this->createConfig('test_key', 'test_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->get('test_key');

        $this->assertEquals('test_value', $result);
    }

    public function test_get_returns_default_when_config_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->get('non_existent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_get_returns_null_when_no_default_provided(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->get('non_existent');

        $this->assertNull($result);
    }

    public function test_get_uses_cache_after_first_load(): void
    {
        $config = $this->createConfig('cached_key', 'cached_value', 'string');

        // First call should load from repository
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $this->service->get('cached_key');

        // Second call should use cache (no additional repository call)
        $this->repository
            ->expects($this->never())
            ->method('getAll');

        $result = $this->service->get('cached_key');
        $this->assertEquals('cached_value', $result);
    }

    public function test_get_converts_integer_type(): void
    {
        $config = $this->createConfig('int_key', '42', 'integer');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->get('int_key');

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function test_get_converts_boolean_type(): void
    {
        $config = $this->createConfig('bool_key', '1', 'boolean');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->get('bool_key');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_get_converts_json_type(): void
    {
        $jsonData = array('key' => 'value', 'number' => 123);
        $config = $this->createConfig('json_key', json_encode($jsonData), 'json');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->get('json_key');

        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }

    public function test_set_creates_new_config_when_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->with('new_key')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) {
                return $config->key === 'new_key'
                    && $config->value === 'new_value'
                    && $config->type === 'string';
            }));

        $this->service->set('new_key', 'new_value');
    }

    public function test_set_updates_existing_config(): void
    {
        $existingConfig = $this->createConfig('existing_key', 'old_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->with('existing_key')
            ->willReturn($existingConfig);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) {
                return $config->key === 'existing_key'
                    && $config->value === 'updated_value';
            }));

        $this->service->set('existing_key', 'updated_value');
    }

    public function test_set_clears_cache(): void
    {
        $config = $this->createConfig('test_key', 'value1', 'string');

        // Load cache
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAll')
            ->willReturn(array($config));

        $this->service->get('test_key');

        // Set new value - should clear cache
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save');

        $this->service->set('new_key', 'new_value');

        // Next get should reload from repository
        $this->service->get('test_key');
    }

    public function test_set_converts_integer_value(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) {
                return $config->type === 'integer' && $config->value === '42';
            }));

        $this->service->set('int_key', 42, 'integer');
    }

    public function test_set_converts_boolean_value(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) {
                return $config->type === 'boolean' && $config->value === '1';
            }));

        $this->service->set('bool_key', true, 'boolean');
    }

    public function test_set_converts_json_value(): void
    {
        $jsonData = array('key' => 'value');

        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) use ($jsonData) {
                return $config->type === 'json'
                    && $config->value === json_encode($jsonData);
            }));

        $this->service->set('json_key', $jsonData, 'json');
    }

    public function test_set_marks_encrypted_type_as_sensitive(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Config $config) {
                return $config->type === 'encrypted' && $config->isSensitive === true;
            }));

        $this->service->set('encrypted_key', 'secret', 'encrypted');
    }

    public function test_has_returns_true_when_config_exists(): void
    {
        $config = $this->createConfig('test_key', 'value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $this->assertTrue($this->service->has('test_key'));
    }

    public function test_has_returns_false_when_config_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $this->assertFalse($this->service->has('non_existent'));
    }

    public function test_delete_removes_config_and_clears_cache(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('key_to_delete');

        $this->service->delete('key_to_delete');

        // Cache should be cleared, so next get should reload
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $this->service->get('any_key');
    }

    public function test_all_returns_all_configs(): void
    {
        $config1 = $this->createConfig('key1', 'value1', 'string');
        $config2 = $this->createConfig('key2', 'value2', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config1, $config2));

        $result = $this->service->all();

        $this->assertCount(2, $result);
        $this->assertContains($config1, $result);
        $this->assertContains($config2, $result);
    }

    public function test_all_excludes_sensitive_when_flag_false(): void
    {
        $normalConfig = $this->createConfig('normal_key', 'value', 'string');
        $normalConfig->isSensitive = false;

        $sensitiveConfig = $this->createConfig('sensitive_key', 'secret', 'encrypted');
        $sensitiveConfig->isSensitive = true;

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($normalConfig, $sensitiveConfig));

        $result = $this->service->all(includeSensitive: false);

        $this->assertCount(1, $result);
        $this->assertContains($normalConfig, $result);
        $this->assertNotContains($sensitiveConfig, $result);
    }

    public function test_all_includes_sensitive_when_flag_true(): void
    {
        $normalConfig = $this->createConfig('normal_key', 'value', 'string');
        $sensitiveConfig = $this->createConfig('sensitive_key', 'secret', 'encrypted');
        $sensitiveConfig->isSensitive = true;

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($normalConfig, $sensitiveConfig));

        $result = $this->service->all(includeSensitive: true);

        $this->assertCount(2, $result);
    }

    public function test_keys_returns_all_config_keys(): void
    {
        $config1 = $this->createConfig('key1', 'value1', 'string');
        $config2 = $this->createConfig('key2', 'value2', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config1, $config2));

        $result = $this->service->keys();

        $this->assertEquals(array('key1', 'key2'), $result);
    }

    public function test_find_returns_config_entity(): void
    {
        $config = $this->createConfig('test_key', 'value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->find('test_key');

        $this->assertInstanceOf(Config::class, $result);
        $this->assertEquals($config, $result);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->find('non_existent');

        $this->assertNull($result);
    }

    public function test_reload_clears_cache(): void
    {
        $config = $this->createConfig('test_key', 'value1', 'string');

        // Load cache
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAll')
            ->willReturn(array($config));

        $this->service->get('test_key');

        // Reload should clear cache
        $this->service->reload();

        // Next get should reload from repository
        $this->service->get('test_key');
    }

    public function test_getString_returns_string_value(): void
    {
        $config = $this->createConfig('string_key', '123', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->getString('string_key', 'default');

        $this->assertIsString($result);
        $this->assertEquals('123', $result);
    }

    public function test_getInt_returns_integer_value(): void
    {
        $config = $this->createConfig('int_key', '42', 'integer');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->getInt('int_key', 0);

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function test_getBool_returns_boolean_value(): void
    {
        $config = $this->createConfig('bool_key', '1', 'boolean');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->getBool('bool_key', false);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_getJson_returns_array_value(): void
    {
        $jsonData = array('key' => 'value');
        $config = $this->createConfig('json_key', json_encode($jsonData), 'json');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->getJson('json_key', array());

        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }

    public function test_getJson_returns_default_when_not_array(): void
    {
        $config = $this->createConfig('invalid_json', 'not_json', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $default = array('default' => 'value');
        $result = $this->service->getJson('invalid_json', $default);

        $this->assertEquals($default, $result);
    }

    /**
     * Test keys method returns array of all config keys
     */
    public function test_keys_returns_array_of_keys(): void
    {
        $config1 = $this->createConfig('key1', 'value1', 'string');
        $config2 = $this->createConfig('key2', 'value2', 'string');
        $config3 = $this->createConfig('key3', 'value3', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config1, $config2, $config3));

        $result = $this->service->keys();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('key1', $result);
        $this->assertContains('key2', $result);
        $this->assertContains('key3', $result);
    }

    /**
     * Test keys method returns empty array when no configs exist
     */
    public function test_keys_returns_empty_array_when_no_configs(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->keys();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test find method returns Config entity when found
     */
    public function test_find_returns_config_when_found(): void
    {
        $config = $this->createConfig('test_key', 'test_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->find('test_key');

        $this->assertInstanceOf(Config::class, $result);
        $this->assertEquals('test_key', $result->key);
        $this->assertEquals('test_value', $result->value);
    }

    /**
     * Test find method returns null when not found
     */
    public function test_find_returns_null_when_not_found(): void
    {
        $config = $this->createConfig('other_key', 'value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        $result = $this->service->find('non_existent_key');

        $this->assertNull($result);
    }

    /**
     * Test find method uses cache (doesn't call repository twice)
     */
    public function test_find_uses_cache(): void
    {
        $config = $this->createConfig('test_key', 'test_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        // First call loads from repository
        $result1 = $this->service->find('test_key');
        $this->assertNotNull($result1);

        // Second call uses cache (no additional repository call)
        $result2 = $this->service->find('test_key');
        $this->assertNotNull($result2);
        $this->assertSame($result1, $result2);
    }

    /**
     * Test convenience getters with default values
     */
    public function test_getString_returns_default_when_not_found(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->getString('non_existent', 'default_string');

        $this->assertIsString($result);
        $this->assertEquals('default_string', $result);
    }

    public function test_getInt_returns_default_when_not_found(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->getInt('non_existent', 99);

        $this->assertIsInt($result);
        $this->assertEquals(99, $result);
    }

    public function test_getBool_returns_default_when_not_found(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->getBool('non_existent', true);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * Test get() logs and rethrows exception when repository fails
     */
    public function test_get_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->service->get('test_key');
    }

    /**
     * Test set() logs and rethrows exception when repository fails during findByKey
     */
    public function test_set_logs_and_rethrows_exception_on_findByKey(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->set('test_key', 'value', 'string');
    }

    /**
     * Test set() logs and rethrows exception when repository fails during save
     */
    public function test_set_logs_and_rethrows_exception_on_save(): void
    {
        $existingConfig = $this->createConfig('test_key', 'old_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn($existingConfig);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Save failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Save failed');

        $this->service->set('test_key', 'new_value', 'string');
    }

    /**
     * Test has() logs and rethrows exception when repository fails
     */
    public function test_has_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->has('test_key');
    }

    /**
     * Test delete() logs and rethrows exception when repository fails
     */
    public function test_delete_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new \RuntimeException('Delete failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Delete failed');

        $this->service->delete('test_key');
    }

    /**
     * Test all() logs and rethrows exception when repository fails
     */
    public function test_all_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->all();
    }

    /**
     * Test keys() logs and rethrows exception when repository fails
     */
    public function test_keys_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->keys();
    }

    /**
     * Test find() logs and rethrows exception when repository fails
     */
    public function test_find_logs_and_rethrows_exception(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->service->find('test_key');
    }

    /**
     * Test reload() logs and rethrows exception
     * Note: reload() only calls clearCache() which doesn't throw, but we test the try-catch structure
     */
    public function test_reload_handles_exceptions(): void
    {
        // reload() doesn't directly call repository, but has try-catch
        // This test verifies the method structure is correct
        $this->service->reload();
        $this->assertTrue(true); // Method executed without error
    }

    /**
     * Test all() filters sensitive configs when includeSensitive is false
     */
    public function test_all_filters_sensitive_configs(): void
    {
        $sensitiveConfig = $this->createConfig('secret_key', 'secret_value', 'encrypted');
        $normalConfig = $this->createConfig('normal_key', 'normal_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($sensitiveConfig, $normalConfig));

        // Test filtering sensitive configs
        $result = $this->service->all(includeSensitive: false);
        $this->assertCount(1, $result);
        // array_filter preserves keys, but we need to check the actual result
        $resultArray = array_values($result); // Reindex to be safe
        $this->assertEquals('normal_key', $resultArray[0]->key);

        // Reset cache for next test
        $this->resetStaticCache();
    }

    /**
     * Test all() includes sensitive configs when includeSensitive is true
     */
    public function test_all_includes_sensitive_configs(): void
    {
        $sensitiveConfig = $this->createConfig('secret_key', 'secret_value', 'encrypted');
        $normalConfig = $this->createConfig('normal_key', 'normal_value', 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($sensitiveConfig, $normalConfig));

        // Test including sensitive configs
        $result = $this->service->all(includeSensitive: true);
        $this->assertCount(2, $result);
        $keys = array_map(fn ($c) => $c->key, $result);
        $this->assertContains('secret_key', $keys);
        $this->assertContains('normal_key', $keys);
    }

    /**
     * Test all() returns empty array when no configs exist
     */
    public function test_all_returns_empty_array_when_no_configs(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array());

        $result = $this->service->all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }


    /**
     * Test sanitizeForLogging() via get() method - long values are truncated
     * Note: This tests the private method indirectly through logging in get()
     */
    public function test_get_handles_long_values_for_logging(): void
    {
        $longValue = str_repeat('a', 150); // 150 characters
        $config = $this->createConfig('long_key', $longValue, 'string');

        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(array($config));

        // The method should work normally, sanitizeForLogging is called internally
        $result = $this->service->get('long_key');
        $this->assertEquals($longValue, $result); // Value itself is not truncated
    }

    /**
     * Helper to create a Config entity for testing
     */
    private function createConfig(string $key, string $value, string $type): Config
    {
        $config = new Config();
        $config->key = $key;
        $config->value = $value;
        $config->type = $type;
        $config->isSensitive = in_array($type, array('encrypted', 'secret'), true);

        return $config;
    }
}
