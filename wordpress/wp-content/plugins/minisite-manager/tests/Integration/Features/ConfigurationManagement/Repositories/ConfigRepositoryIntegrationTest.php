<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for ConfigRepository
 *
 * Tests ConfigRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisite_config table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryIntegrationTest extends BaseIntegrationTest
{
    private ConfigRepository $repository;

    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    protected function setupTestSpecificServices(): void
    {
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);
    }

    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%' OR config_key IN ('to_delete', 'encrypted_key', 'alpha', 'zebra')"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_save_and_find_config(): void
    {
        $config = new Config();
        $config->key = 'test_key';
        $config->type = 'string';
        $config->setTypedValue('test_value');

        $saved = $this->repository->save($config);

        $this->assertNotNull($saved->id);

        $found = $this->repository->findByKey('test_key');

        $this->assertNotNull($found);
        $this->assertEquals('test_key', $found->key);
        $this->assertEquals('test_value', $found->getTypedValue());
    }

    public function test_getAll_returns_all_configs_ordered_by_key(): void
    {
        // Create multiple configs
        $config1 = new Config();
        $config1->key = 'zebra';
        $config1->type = 'string';
        $config1->setTypedValue('value1');

        $config2 = new Config();
        $config2->key = 'alpha';
        $config2->type = 'string';
        $config2->setTypedValue('value2');

        $this->repository->save($config1);
        $this->repository->save($config2);

        $all = $this->repository->getAll();

        // Filter to our test data (might have other configs from migrations/seeding)
        $testConfigs = array_filter($all, fn ($c) => in_array($c->key, array('alpha', 'zebra')));
        $testConfigs = array_values($testConfigs); // Re-index

        $this->assertGreaterThanOrEqual(2, count($testConfigs), 'Should have at least 2 test configs');
        $this->assertEquals('alpha', $testConfigs[0]->key);
        $this->assertEquals('zebra', $testConfigs[1]->key);
    }

    public function test_delete_removes_config(): void
    {
        $config = new Config();
        $config->key = 'to_delete';
        $config->type = 'string';
        $config->setTypedValue('value');

        $this->repository->save($config);

        $this->assertNotNull($this->repository->findByKey('to_delete'));

        $this->repository->delete('to_delete');

        $this->assertNull($this->repository->findByKey('to_delete'));
    }

    public function test_encrypted_config_stores_encrypted_value(): void
    {
        // Define encryption key for testing (generate a random key)
        if (! defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $originalValue = 'secret_value_12345';

        $config = new Config();
        $config->key = 'encrypted_key';
        $config->type = 'encrypted';
        $config->setTypedValue($originalValue);

        $this->repository->save($config);

        // Verify raw value in DB is encrypted (not plain text)
        // Note: Uses wp_minisite_config (with prefix) via TablePrefixListener
        $rawValue = $this->em->getConnection()
            ->fetchOne("SELECT config_value FROM wp_minisite_config WHERE config_key = ?", array('encrypted_key'));

        // Verify stored value is encrypted
        $this->assertNotEquals($originalValue, $rawValue, 'Stored value should be encrypted, not plain text');
        $this->assertNotEmpty($rawValue, 'Encrypted value should not be empty');

        // Verify we can decrypt it back to original
        $found = $this->repository->findByKey('encrypted_key');

        $this->assertNotNull($found, 'Config should be found');
        $this->assertEquals('encrypted', $found->type, 'Type should be encrypted');
        $this->assertEquals($originalValue, $found->getTypedValue(), 'Decrypted value should match original');

        // Test updating encrypted value
        $newValue = 'new_secret_67890';
        $found->setTypedValue($newValue);
        $this->repository->save($found);

        // Verify new value is encrypted in DB
        $newRawValue = $this->em->getConnection()
            ->fetchOne("SELECT config_value FROM wp_minisite_config WHERE config_key = ?", array('encrypted_key'));

        $this->assertNotEquals($newValue, $newRawValue, 'New stored value should be encrypted');
        $this->assertNotEquals($rawValue, $newRawValue, 'New encrypted value should be different from old');

        // Verify we can decrypt new value
        $updated = $this->repository->findByKey('encrypted_key');
        $this->assertEquals($newValue, $updated->getTypedValue(), 'Decrypted new value should match');
    }
}
