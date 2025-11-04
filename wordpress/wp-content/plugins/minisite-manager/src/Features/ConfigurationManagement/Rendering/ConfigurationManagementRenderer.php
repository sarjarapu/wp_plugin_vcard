<?php

namespace Minisite\Features\ConfigurationManagement\Rendering;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;

/**
 * ConfigurationManagementRenderer
 *
 * SINGLE RESPONSIBILITY: Render configuration admin UI templates
 */
final class ConfigurationManagementRenderer
{
    /**
     * Register Timber template locations
     */
    private function registerTimberLocations(): void
    {
        $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? [],
                    [$base]
                )
            )
        );
    }

    /**
     * Render the configuration admin page
     */
    public function render(array $configs, array $messages, string $nonce, string $deleteNonce): void
    {
        if (!class_exists('Timber\\Timber')) {
            wp_die('Timber plugin is required for admin pages.');
        }

        $this->registerTimberLocations();

        $context = [
            'page_title' => 'Minisite Configuration',
            'page_description' => 'Manage application settings, API keys, and integration credentials.',
            'configs' => $configs,
            'nonce' => $nonce,
            'delete_nonce' => $deleteNonce,
            'admin_url' => admin_url('admin.php'),
            'admin_post_url' => admin_url('admin-post.php'),
            'messages' => $messages,
        ];

        \Timber\Timber::render('views/admin-config.twig', $context);
    }

    /**
     * Prepare config for template
     */
    public function prepareConfigForTemplate(Config $config): array
    {
        $value = $config->getTypedValue();

        // Default configs are required and cannot be deleted
        $defaultConfigs = ['openai_api_key', 'pii_encryption_key', 'max_reviews_per_page'];
        $isRequired = $config->isRequired || in_array($config->key, $defaultConfigs, true);

        return [
            'key' => $config->key,
            'display_name' => $this->formatKeyName($config->key),
            'value' => $value,
            'display_value' => $config->isSensitive && $value ? $this->maskValue((string) $value) : $value,
            'type' => $config->type,
            'description' => $config->description,
            'is_sensitive' => $config->isSensitive,
            'is_required' => $isRequired,
        ];
    }

    /**
     * Mask sensitive value for display
     */
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return '••••';
        }
        return '••••••••' . substr($value, -4);
    }

    /**
     * Format key name for display
     */
    private function formatKeyName(string $key): string
    {
        // Define acronyms that should be all uppercase
        $acronyms = ['openai', 'pii', 'api', 'id', 'url', 'http', 'https', 'ssl', 'tls', 'oauth', 'jwt'];

        // Split by underscore and capitalize each word
        $words = explode('_', $key);
        $formatted = [];

        foreach ($words as $word) {
            $lowerWord = strtolower($word);
            if (in_array($lowerWord, $acronyms, true)) {
                $formatted[] = strtoupper($word);
            } else {
                $formatted[] = ucfirst($word);
            }
        }

        return implode(' ', $formatted);
    }
}
