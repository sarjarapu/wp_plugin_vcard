<?php

namespace Minisite\Infrastructure\Config;

use Minisite\Features\AppConfig\Services\AppConfigService;
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
     * Seed default configurations
     * Only creates missing configs (preserves existing values)
     */
    public function seedDefaults(AppConfigService $configManager): void
    {
        $this->logger->info("seedDefaults() entry");

        try {
            $defaults = [
                // API Keys (empty, to be filled by admin)
                'openai_api_key' => [
                    'value' => '',
                    'type' => 'encrypted',
                    'description' => 'OpenAI API key for AI features'
                ],

                // Encryption
                'pii_encryption_key' => [
                    'value' => '',
                    'type' => 'encrypted',
                    'description' => 'Key for encrypting PII (Personally Identifiable Information) in reviews'
                ],

                // Review Settings
                'max_reviews_per_page' => [
                    'value' => 20,
                    'type' => 'integer',
                    'description' => 'Maximum number of reviews to display per page'
                ],
            ];

            $created = 0;
            foreach ($defaults as $key => $config) {
                // Only create if doesn't exist (preserve existing values)
                if (!$configManager->has($key)) {
                    $configManager->set(
                        $key,
                        $config['value'],
                        $config['type'],
                        $config['description'] // description is always present in defaults array
                    );
                    $created++;
                }
            }

            $this->logger->info("seedDefaults() exit", [
                'created' => $created,
                'total_defaults' => count($defaults),
            ]);
        } catch (\Exception $e) {
            $this->logger->error("seedDefaults() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
}
