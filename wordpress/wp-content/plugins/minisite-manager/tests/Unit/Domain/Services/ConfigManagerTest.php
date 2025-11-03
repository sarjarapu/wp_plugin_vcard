<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use Minisite\Domain\Entities\Config;
use Minisite\Domain\Services\ConfigManager;
use Minisite\Infrastructure\Persistence\Repositories\ConfigRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ConfigManager
 */
final class ConfigManagerTest extends TestCase
{
    private ConfigRepositoryInterface|MockObject $repository;
    private ConfigManager $configManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define encryption key for tests that use encrypted type
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }
        
        // Reset static cache between tests
        $this->resetStaticCache();
        
        $this->repository = $this->createMock(ConfigRepositoryInterface::class);
        $this->configManager = new ConfigManager($this->repository);
    }
    
    protected function tearDown(): void
    {
        // Clean up static cache after each test
        $this->resetStaticCache();
        parent::tearDown();
    }
    
    /**
     * Reset ConfigManager static cache using reflection
     */
    private function resetStaticCache(): void
    {
        $reflection = new \ReflectionClass(ConfigManager::class);
        
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
            ->willReturn([$config]);
        
        $result = $this->configManager->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }
    
    public function test_get_returns_default_when_config_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);
        
        $result = $this->configManager->get('non_existent', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }
    
    public function test_get_returns_null_when_no_default_provided(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);
        
        $result = $this->configManager->get('non_existent');
        
        $this->assertNull($result);
    }
    
    public function test_get_uses_cache_after_first_load(): void
    {
        $config = $this->createConfig('cached_key', 'cached_value', 'string');
        
        // First call should load from repository
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $this->configManager->get('cached_key');
        
        // Second call should use cache (no additional repository call)
        $this->repository
            ->expects($this->never())
            ->method('getAll');
        
        $result = $this->configManager->get('cached_key');
        $this->assertEquals('cached_value', $result);
    }
    
    public function test_get_converts_integer_type(): void
    {
        $config = $this->createConfig('int_key', '42', 'integer');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->get('int_key');
        
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }
    
    public function test_get_converts_boolean_type(): void
    {
        $config = $this->createConfig('bool_key', '1', 'boolean');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->get('bool_key');
        
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
    
    public function test_get_converts_json_type(): void
    {
        $jsonData = ['key' => 'value', 'number' => 123];
        $config = $this->createConfig('json_key', json_encode($jsonData), 'json');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->get('json_key');
        
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
        
        $this->configManager->set('new_key', 'new_value');
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
        
        $this->configManager->set('existing_key', 'updated_value');
    }
    
    public function test_set_clears_cache(): void
    {
        $config = $this->createConfig('test_key', 'value1', 'string');
        
        // Load cache
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAll')
            ->willReturn([$config]);
        
        $this->configManager->get('test_key');
        
        // Set new value - should clear cache
        $this->repository
            ->expects($this->once())
            ->method('findByKey')
            ->willReturn(null);
        
        $this->repository
            ->expects($this->once())
            ->method('save');
        
        $this->configManager->set('new_key', 'new_value');
        
        // Next get should reload from repository
        $this->configManager->get('test_key');
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
        
        $this->configManager->set('int_key', 42, 'integer');
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
        
        $this->configManager->set('bool_key', true, 'boolean');
    }
    
    public function test_set_converts_json_value(): void
    {
        $jsonData = ['key' => 'value'];
        
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
        
        $this->configManager->set('json_key', $jsonData, 'json');
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
        
        $this->configManager->set('encrypted_key', 'secret', 'encrypted');
    }
    
    public function test_has_returns_true_when_config_exists(): void
    {
        $config = $this->createConfig('test_key', 'value', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $this->assertTrue($this->configManager->has('test_key'));
    }
    
    public function test_has_returns_false_when_config_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);
        
        $this->assertFalse($this->configManager->has('non_existent'));
    }
    
    public function test_delete_removes_config_and_clears_cache(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('key_to_delete');
        
        $this->configManager->delete('key_to_delete');
        
        // Cache should be cleared, so next get should reload
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);
        
        $this->configManager->get('any_key');
    }
    
    public function test_all_returns_all_configs(): void
    {
        $config1 = $this->createConfig('key1', 'value1', 'string');
        $config2 = $this->createConfig('key2', 'value2', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config1, $config2]);
        
        $result = $this->configManager->all();
        
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
            ->willReturn([$normalConfig, $sensitiveConfig]);
        
        $result = $this->configManager->all(includeSensitive: false);
        
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
            ->willReturn([$normalConfig, $sensitiveConfig]);
        
        $result = $this->configManager->all(includeSensitive: true);
        
        $this->assertCount(2, $result);
    }
    
    public function test_keys_returns_all_config_keys(): void
    {
        $config1 = $this->createConfig('key1', 'value1', 'string');
        $config2 = $this->createConfig('key2', 'value2', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config1, $config2]);
        
        $result = $this->configManager->keys();
        
        $this->assertEquals(['key1', 'key2'], $result);
    }
    
    public function test_find_returns_config_entity(): void
    {
        $config = $this->createConfig('test_key', 'value', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->find('test_key');
        
        $this->assertInstanceOf(Config::class, $result);
        $this->assertEquals($config, $result);
    }
    
    public function test_find_returns_null_when_not_exists(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);
        
        $result = $this->configManager->find('non_existent');
        
        $this->assertNull($result);
    }
    
    public function test_reload_clears_cache(): void
    {
        $config = $this->createConfig('test_key', 'value1', 'string');
        
        // Load cache
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAll')
            ->willReturn([$config]);
        
        $this->configManager->get('test_key');
        
        // Reload should clear cache
        $this->configManager->reload();
        
        // Next get should reload from repository
        $this->configManager->get('test_key');
    }
    
    public function test_getString_returns_string_value(): void
    {
        $config = $this->createConfig('string_key', '123', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->getString('string_key', 'default');
        
        $this->assertIsString($result);
        $this->assertEquals('123', $result);
    }
    
    public function test_getInt_returns_integer_value(): void
    {
        $config = $this->createConfig('int_key', '42', 'integer');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->getInt('int_key', 0);
        
        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }
    
    public function test_getBool_returns_boolean_value(): void
    {
        $config = $this->createConfig('bool_key', '1', 'boolean');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->getBool('bool_key', false);
        
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
    
    public function test_getJson_returns_array_value(): void
    {
        $jsonData = ['key' => 'value'];
        $config = $this->createConfig('json_key', json_encode($jsonData), 'json');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $result = $this->configManager->getJson('json_key', []);
        
        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }
    
    public function test_getJson_returns_default_when_not_array(): void
    {
        $config = $this->createConfig('invalid_json', 'not_json', 'string');
        
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$config]);
        
        $default = ['default' => 'value'];
        $result = $this->configManager->getJson('invalid_json', $default);
        
        $this->assertEquals($default, $result);
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
        $config->isSensitive = in_array($type, ['encrypted', 'secret'], true);
        return $config;
    }
}

