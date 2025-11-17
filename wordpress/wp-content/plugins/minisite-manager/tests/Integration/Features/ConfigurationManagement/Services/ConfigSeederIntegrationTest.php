<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Services;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Features\ConfigurationManagement\Services\ConfigSeeder;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for ConfigSeeder
 *
 * Tests ConfigSeeder against real MySQL database.
 * Validates that configs are actually created in the database with correct values.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ConfigSeeder::class)]
final class ConfigSeederIntegrationTest extends BaseIntegrationTest
{
    private ConfigRepository $repository;
    private ConfigurationManagementService $configService;
    private ConfigSeeder $seeder;

    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    protected function setupTestSpecificServices(): void
    {
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);
        $this->configService = new ConfigurationManagementService($this->repository);
        $this->seeder = new ConfigSeeder();
    }

    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement('DELETE FROM wp_minisite_config');
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    protected function tearDown(): void
    {
        // Reset service static cache before parent cleanup
        $reflection = new \ReflectionClass(ConfigurationManagementService::class);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, null);

        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);

        parent::tearDown();
    }

    /**
     * Test seedDefaults creates all configs from JSON file
     */
    public function test_seedDefaults_creates_all_configs_from_json(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify all expected configs exist in database
        $expectedConfigs = array(
            'openai_api_key',
            'pii_encryption_key',
            'max_reviews_per_page',
            'max_minisites_list_limit',
            'max_versions_list_limit',
        );

        foreach ($expectedConfigs as $key) {
            $config = $this->repository->findByKey($key);
            $this->assertNotNull($config, "Config '{$key}' should exist in database");
            $this->assertInstanceOf(Config::class, $config);
            $this->assertSame($key, $config->key);
        }
    }

    /**
     * Test seedDefaults sets correct values from JSON
     */
    public function test_seedDefaults_sets_correct_values_from_json(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify values match JSON file
        $maxReviews = $this->configService->getInt('max_reviews_per_page');
        $this->assertEquals(20, $maxReviews, 'max_reviews_per_page should be 20');

        $maxMinisites = $this->configService->getInt('max_minisites_list_limit');
        $this->assertEquals(50, $maxMinisites, 'max_minisites_list_limit should be 50');

        $maxVersions = $this->configService->getInt('max_versions_list_limit');
        $this->assertEquals(50, $maxVersions, 'max_versions_list_limit should be 50');

        // Verify encrypted keys are empty strings
        $openaiKey = $this->configService->getString('openai_api_key');
        $this->assertEmpty($openaiKey, 'openai_api_key should be empty initially');

        $piiKey = $this->configService->getString('pii_encryption_key');
        $this->assertEmpty($piiKey, 'pii_encryption_key should be empty initially');
    }

    /**
     * Test seedDefaults sets correct types from JSON
     */
    public function test_seedDefaults_sets_correct_types_from_json(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify types
        $openaiConfig = $this->repository->findByKey('openai_api_key');
        $this->assertSame('encrypted', $openaiConfig->type);

        $piiConfig = $this->repository->findByKey('pii_encryption_key');
        $this->assertSame('encrypted', $piiConfig->type);

        $maxReviewsConfig = $this->repository->findByKey('max_reviews_per_page');
        $this->assertSame('integer', $maxReviewsConfig->type);

        $maxMinisitesConfig = $this->repository->findByKey('max_minisites_list_limit');
        $this->assertSame('integer', $maxMinisitesConfig->type);
    }

    /**
     * Test seedDefaults sets correct descriptions from JSON
     */
    public function test_seedDefaults_sets_correct_descriptions_from_json(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify descriptions
        $openaiConfig = $this->repository->findByKey('openai_api_key');
        $this->assertSame('OpenAI API key for AI features', $openaiConfig->description);

        $maxReviewsConfig = $this->repository->findByKey('max_reviews_per_page');
        $this->assertSame('Maximum number of reviews to display per page', $maxReviewsConfig->description);

        $maxMinisitesConfig = $this->repository->findByKey('max_minisites_list_limit');
        $this->assertSame('Maximum number of minisites to display in listing pages', $maxMinisitesConfig->description);
    }

    /**
     * Test seedDefaults preserves existing configs (doesn't overwrite)
     */
    public function test_seedDefaults_preserves_existing_configs(): void
    {
        // Create an existing config with a custom value
        $this->configService->set('max_reviews_per_page', 30, 'integer', 'Custom description');

        // Seed defaults (should not overwrite existing)
        $this->seeder->seedDefaults($this->configService);

        // Verify existing value is preserved
        $value = $this->configService->getInt('max_reviews_per_page');
        $this->assertEquals(30, $value, 'Existing config value should be preserved');

        $config = $this->repository->findByKey('max_reviews_per_page');
        $this->assertSame('Custom description', $config->description, 'Existing description should be preserved');
    }

    /**
     * Test seedDefaults only creates missing configs
     */
    public function test_seedDefaults_only_creates_missing_configs(): void
    {
        // Create one config manually
        $this->configService->set('openai_api_key', 'existing-key', 'encrypted', 'Existing key');

        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify existing config is preserved
        $openaiKey = $this->configService->getString('openai_api_key');
        $this->assertSame('existing-key', $openaiKey, 'Existing config should be preserved');

        // Verify other configs are created
        $this->assertTrue($this->configService->has('pii_encryption_key'), 'Missing config should be created');
        $this->assertTrue($this->configService->has('max_reviews_per_page'), 'Missing config should be created');
    }

    /**
     * Test seedDefaults can be called multiple times safely
     */
    public function test_seedDefaults_can_be_called_multiple_times(): void
    {
        // Seed first time
        $this->seeder->seedDefaults($this->configService);
        $firstCount = count($this->configService->keys());

        // Seed second time
        $this->seeder->seedDefaults($this->configService);
        $secondCount = count($this->configService->keys());

        // Should have same number of configs (no duplicates)
        $this->assertEquals($firstCount, $secondCount, 'Multiple calls should not create duplicates');
    }

    /**
     * Test seedDefaults creates configs with correct isSensitive flag
     */
    public function test_seedDefaults_sets_correct_isSensitive_flag(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Encrypted types should be sensitive
        $openaiConfig = $this->repository->findByKey('openai_api_key');
        $this->assertTrue($openaiConfig->isSensitive, 'Encrypted config should be marked as sensitive');

        $piiConfig = $this->repository->findByKey('pii_encryption_key');
        $this->assertTrue($piiConfig->isSensitive, 'Encrypted config should be marked as sensitive');

        // Integer types should not be sensitive
        $maxReviewsConfig = $this->repository->findByKey('max_reviews_per_page');
        $this->assertFalse($maxReviewsConfig->isSensitive, 'Integer config should not be sensitive');
    }

    /**
     * Test seedDefaults creates configs that can be retrieved via service
     */
    public function test_seedDefaults_creates_configs_retrievable_via_service(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Verify configs can be retrieved with typed getters
        $maxReviews = $this->configService->getInt('max_reviews_per_page', 0);
        $this->assertIsInt($maxReviews);
        $this->assertEquals(20, $maxReviews);

        $maxMinisites = $this->configService->getInt('max_minisites_list_limit', 0);
        $this->assertIsInt($maxMinisites);
        $this->assertEquals(50, $maxMinisites);

        // Verify encrypted configs can be retrieved (even if empty)
        $openaiKey = $this->configService->getString('openai_api_key', '');
        $this->assertIsString($openaiKey);
    }

    /**
     * Test seedDefaults creates all configs from JSON file in database
     * Validates actual database records
     */
    public function test_seedDefaults_creates_database_records(): void
    {
        // Seed defaults
        $this->seeder->seedDefaults($this->configService);

        // Query database directly to verify records exist
        $connection = $this->em->getConnection();
        $result = $connection->executeQuery(
            'SELECT config_key, config_type, config_value, description, is_sensitive
             FROM wp_minisite_config
             ORDER BY config_key'
        );

        $records = $result->fetchAllAssociative();
        $this->assertGreaterThanOrEqual(5, count($records), 'Should have at least 5 config records');

        // Verify specific records exist
        $keys = array_column($records, 'config_key');
        $this->assertContains('openai_api_key', $keys);
        $this->assertContains('pii_encryption_key', $keys);
        $this->assertContains('max_reviews_per_page', $keys);
        $this->assertContains('max_minisites_list_limit', $keys);
        $this->assertContains('max_versions_list_limit', $keys);

        // Verify one record in detail
        $maxReviewsRecord = null;
        foreach ($records as $record) {
            if ($record['config_key'] === 'max_reviews_per_page') {
                $maxReviewsRecord = $record;

                break;
            }
        }

        $this->assertNotNull($maxReviewsRecord, 'max_reviews_per_page record should exist');
        $this->assertSame('integer', $maxReviewsRecord['config_type']);
        $this->assertSame('20', $maxReviewsRecord['config_value']);
        $this->assertSame('Maximum number of reviews to display per page', $maxReviewsRecord['description']);
        $this->assertEquals(0, (int) $maxReviewsRecord['is_sensitive'], 'Integer config should not be sensitive');
    }
}
