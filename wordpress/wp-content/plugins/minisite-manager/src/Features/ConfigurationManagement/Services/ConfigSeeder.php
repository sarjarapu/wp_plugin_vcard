<?php

namespace Minisite\Features\ConfigurationManagement\Services;

use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

class ConfigSeeder
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-seeder');
    }

    /**
     * Seed default configurations from JSON file
     * Only creates missing configs (preserves existing values)
     */
    public function seedDefaults(ConfigurationManagementService $configManager): void
    {
        $this->logger->info("seedDefaults() entry");

        try {
            $defaults = $this->loadDefaultsFromJson();

            $created = 0;
            foreach ($defaults as $config) {
                $key = $config['key'];

                // Only create if doesn't exist (preserve existing values)
                if (! $configManager->has($key)) {
                    $configManager->set(
                        $key,
                        $config['value'],
                        $config['type'],
                        $config['description'] ?? null
                    );
                    $created++;
                }
            }

            $this->logger->info("seedDefaults() exit", array(
                'created' => $created,
                'total_defaults' => count($defaults),
            ));
        } catch (\Exception $e) {
            $this->logger->error("seedDefaults() failed", array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ));

            throw $e;
        }
    }

    /**
     * Load default configurations from JSON file
     *
     * @return array Array of config arrays with keys: key, value, type, description, is_sensitive, is_required
     */
    private function loadDefaultsFromJson(): array
    {
        $jsonPath = MINISITE_PLUGIN_DIR . 'data/json/config/default-config.json';

        $validationResult = $this->validateJsonFile($jsonPath);
        if (! $validationResult['valid']) {
            $this->logger->warning("Config JSON file validation failed, using fallback defaults", array(
                'json_path' => $jsonPath,
                'reason' => $validationResult['reason'],
            ));

            return $this->getFallbackDefaults();
        }

        $data = $validationResult['data'];

        $this->logger->debug("Loaded configs from JSON", array(
            'count' => count($data['configs']),
        ));

        return $data['configs'];
    }

    /**
     * Validate JSON config file
     * Checks if file exists, is readable, contains valid JSON, and has correct structure
     *
     * @param string $jsonPath Path to JSON file
     * @return array{valid: bool, reason?: string, data?: array} Validation result with parsed data if valid
     */
    private function validateJsonFile(string $jsonPath): array
    {
        // Check if file exists
        if (! file_exists($jsonPath)) {
            return array(
                'valid' => false,
                'reason' => 'File does not exist',
            );
        }

        // Check if file is readable
        if (! is_readable($jsonPath)) {
            return array(
                'valid' => false,
                'reason' => 'File is not readable',
            );
        }

        // Read file content
        $jsonContent = file_get_contents($jsonPath);
        if ($jsonContent === false) {
            return array(
                'valid' => false,
                'reason' => 'Failed to read file',
            );
        }

        // Validate JSON syntax
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'valid' => false,
                'reason' => 'Invalid JSON: ' . json_last_error_msg(),
            );
        }

        // Validate structure: must have 'configs' key with array value
        if (! isset($data['configs']) || ! is_array($data['configs'])) {
            return array(
                'valid' => false,
                'reason' => 'Invalid structure: missing or invalid "configs" array',
            );
        }

        return array(
            'valid' => true,
            'data' => $data,
        );
    }

    /**
     * Fallback defaults if JSON file is missing or invalid
     * This ensures the system still works even if JSON file is corrupted
     *
     * @return array
     */
    private function getFallbackDefaults(): array
    {
        return array(
            array(
                'key' => 'openai_api_key',
                'value' => '',
                'type' => 'encrypted',
                'description' => 'OpenAI API key for AI features',
                'is_sensitive' => true,
                'is_required' => true,
            ),
            array(
                'key' => 'pii_encryption_key',
                'value' => '',
                'type' => 'encrypted',
                'description' => 'Key for encrypting PII (Personally Identifiable Information) in reviews',
                'is_sensitive' => true,
                'is_required' => true,
            ),
            array(
                'key' => 'max_reviews_per_page',
                'value' => 20,
                'type' => 'integer',
                'description' => 'Maximum number of reviews to display per page',
                'is_sensitive' => false,
                'is_required' => true,
            ),
        );
    }
}
