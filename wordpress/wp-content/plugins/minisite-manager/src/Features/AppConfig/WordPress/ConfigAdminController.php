<?php

namespace Minisite\Features\AppConfig\WordPress;

use Minisite\Domain\Services\ConfigManager;
use Minisite\Domain\Entities\Config;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

class ConfigAdminController
{
    private LoggerInterface $logger;
    
    public function __construct()
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-admin-controller');
    }
    
    private function getConfigManager(): ConfigManager
    {
        if (!isset($GLOBALS['minisite_config_manager'])) {
            throw new \RuntimeException('ConfigManager not initialized');
        }
        
        return $GLOBALS['minisite_config_manager'];
    }
    
    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $this->logger->debug("handleRequest() entry", [
            'method' => $_SERVER['REQUEST_METHOD'],
        ]);
        
        // Verify nonce
        if (!isset($_POST['minisite_config_nonce']) || 
            !wp_verify_nonce($_POST['minisite_config_nonce'], 'minisite_config_save')) {
            wp_die('Security check failed');
        }
        
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['config_key'])) {
            $this->handleDelete($_POST['config_key']);
            return;
        }
        
        // Handle save action
        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $this->handleSave();
            return;
        }
    }
    
    private function handleSave(): void
    {
        $this->logger->debug("handleSave() entry");
        
        $configManager = $this->getConfigManager();
        $updated = 0;
        $errors = [];
        
        // Process each config field
        foreach ($_POST['config'] ?? [] as $key => $data) {
            try {
                $value = $data['value'] ?? '';
                $type = $data['type'] ?? 'string';
                $description = $data['description'] ?? null;
                
                // Special handling: if sensitive field and value is masked, don't update
                $existing = $configManager->find($key);
                if ($existing && $existing->isSensitive && $this->isMaskedValue($value)) {
                    continue; // Skip masked values (user didn't change it)
                }
                
                $configManager->set($key, $value, $type, $description);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to save {$key}: " . $e->getMessage();
                $this->logger->error("handleSave() failed for key", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Handle new config addition
        if (!empty($_POST['new_config_key'])) {
            $key = sanitize_text_field($_POST['new_config_key']);
            $value = $_POST['new_config_value'] ?? '';
            $type = $_POST['new_config_type'] ?? 'string';
            $description = sanitize_textarea_field($_POST['new_config_description'] ?? '');
            
            // Validate key format (lowercase with underscores only)
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $errors[] = "Invalid key format. Use lowercase letters, numbers, and underscores only (e.g., 'whatsapp_access_token')";
            } else {
                try {
                    $configManager->set($key, $value, $type, $description);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to add new config: " . $e->getMessage();
                    $this->logger->error("handleSave() failed for new config", [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        // Show success/error messages
        if ($updated > 0) {
            add_settings_error(
                'minisite_config',
                'config_saved',
                sprintf('%d configuration(s) saved successfully.', $updated),
                'updated'
            );
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error('minisite_config', 'config_error', $error, 'error');
            }
        }
        
        $this->logger->debug("handleSave() exit", [
            'updated' => $updated,
            'errors' => count($errors),
        ]);
    }
    
    private function handleDelete(string $key): void
    {
        $this->logger->debug("handleDelete() entry", [
            'key' => $key,
        ]);
        
        try {
            // Prevent deletion of required/default configurations
            $defaultConfigs = ['openai_api_key', 'pii_encryption_key', 'max_reviews_per_page'];
            if (in_array($key, $defaultConfigs, true)) {
                add_settings_error(
                    'minisite_config',
                    'config_error',
                    'Cannot delete required configuration: ' . $key,
                    'error'
                );
                
                $this->logger->warning("handleDelete() blocked - required config", [
                    'key' => $key,
                ]);
                return;
            }
            
            $configManager = $this->getConfigManager();
            $config = $configManager->find($key);
            
            // Also check if config is marked as required in database
            if ($config && $config->isRequired) {
                add_settings_error(
                    'minisite_config',
                    'config_error',
                    'Cannot delete required configuration: ' . $key,
                    'error'
                );
                
                $this->logger->warning("handleDelete() blocked - isRequired flag", [
                    'key' => $key,
                ]);
                return;
            }
            
            $configManager->delete($key);
            
            add_settings_error(
                'minisite_config',
                'config_deleted',
                'Configuration deleted successfully.',
                'updated'
            );
            
            $this->logger->debug("handleDelete() exit", [
                'key' => $key,
            ]);
        } catch (\Exception $e) {
            add_settings_error(
                'minisite_config',
                'config_error',
                'Failed to delete configuration: ' . $e->getMessage(),
                'error'
            );
            
            $this->logger->error("handleDelete() failed", [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    private function isMaskedValue(string $value): bool
    {
        // Check if value is masked (e.g., "••••••••1234")
        return str_starts_with($value, '••••');
    }
    
    public function render(): void
    {
        $this->logger->debug("render() entry");
        
        if (!class_exists('Timber\\Timber')) {
            wp_die('Timber plugin is required for admin pages.');
        }
        
        $this->registerTimberLocations();
        
        $configManager = $this->getConfigManager();
        $configs = $configManager->all(includeSensitive: true);
        
        // Prepare configs for template (no grouping needed for fixed configs)
        $preparedConfigs = array_map(
            fn($config) => $this->prepareConfigForTemplate($config),
            $configs
        );
        
        $context = [
            'page_title' => 'Minisite Configuration',
            'page_description' => 'Manage application settings, API keys, and integration credentials.',
            'configs' => $preparedConfigs,
            'nonce' => wp_create_nonce('minisite_config_save'),
            'delete_nonce' => wp_create_nonce('minisite_config_delete'),
            'admin_url' => admin_url('admin.php'),
            'admin_post_url' => admin_url('admin-post.php'),
            'messages' => $this->getSettingsMessages(),
        ];
        
        \Timber\Timber::render('views/admin-config.twig', $context);
        
        $this->logger->debug("render() exit");
    }
    
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
    
    private function groupConfigs(array $configs): array
    {
        $grouped = [
            'whatsapp' => [],
            'api_keys' => [],
            'review_settings' => [],
            'general' => [],
        ];
        
        foreach ($configs as $config) {
            $key = strtolower($config->key);
            
            if (str_contains($key, 'whatsapp')) {
                $grouped['whatsapp'][] = $this->prepareConfigForTemplate($config);
            } elseif (str_contains($key, 'api') || str_contains($key, 'key') || str_contains($key, 'token')) {
                $grouped['api_keys'][] = $this->prepareConfigForTemplate($config);
            } elseif (str_contains($key, 'review')) {
                $grouped['review_settings'][] = $this->prepareConfigForTemplate($config);
            } else {
                $grouped['general'][] = $this->prepareConfigForTemplate($config);
            }
        }
        
        // Remove empty groups
        return array_filter($grouped, fn($items) => !empty($items));
    }
    
    private function prepareConfigForTemplate(Config $config): array
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
    
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return '••••';
        }
        return '••••••••' . substr($value, -4);
    }
    
    private function formatKeyName(string $key): string
    {
        // Convert "openai_api_key" to "OpenAI API Key"
        // Convert "pii_encryption_key" to "PII Encryption Key"
        // Convert "max_reviews_per_page" to "Max Reviews Per Page"
        
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
    
    private function getSettingsMessages(): array
    {
        $messages = [];
        $errors = get_settings_errors('minisite_config');
        
        foreach ($errors as $error) {
            $messages[] = [
                'type' => $error['type'],
                'message' => $error['message'],
            ];
        }
        
        return $messages;
    }
}

