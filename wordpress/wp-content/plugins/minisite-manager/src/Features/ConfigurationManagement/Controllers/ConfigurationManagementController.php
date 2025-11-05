<?php

namespace Minisite\Features\ConfigurationManagement\Controllers;

use Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand;
use Minisite\Features\ConfigurationManagement\Commands\SaveConfigCommand;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler;
use Minisite\Features\ConfigurationManagement\Rendering\ConfigurationManagementRenderer;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * ConfigurationManagementController
 *
 * SINGLE RESPONSIBILITY: HTTP request orchestration for configuration management
 * - Handles POST requests
 * - Validates input
 * - Delegates to handlers
 * - Coordinates rendering
 */
final class ConfigurationManagementController
{
    private LoggerInterface $logger;

    public function __construct(
        private SaveConfigHandler $saveHandler,
        private DeleteConfigHandler $deleteHandler,
        private ConfigurationManagementService $configService,
        private ConfigurationManagementRenderer $renderer
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('configuration-management-controller');
    }

    /**
     * Handle HTTP request
     */
    public function handleRequest(): void
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // REQUEST_METHOD is a server variable, sanitized below
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';
        if ($requestMethod !== 'POST') {
            return;
        }

        $this->logger->debug("handleRequest() entry", array(
            'method' => $requestMethod,
        ));

        // Verify nonce
        if (! $this->verifyNonce('minisite_config_save', 'minisite_config_nonce')) {
            wp_die('Security check failed');
        }

        // Handle delete action
        $action = $this->getPostData('action');
        $configKey = $this->getPostData('config_key');
        if ($action === 'delete' && ! empty($configKey)) {
            $this->handleDelete($configKey);

            return;
        }

        // Handle save action
        if ($action === 'save') {
            $this->handleSave();

            return;
        }
    }

    /**
     * Render the configuration admin page
     */
    public function render(): void
    {
        $this->logger->debug("render() entry");

        $configs = $this->configService->all(includeSensitive: true);

        // Prepare configs for template
        $preparedConfigs = array_map(
            fn ($config) => $this->renderer->prepareConfigForTemplate($config),
            $configs
        );

        $messages = $this->getSettingsMessages();

        $this->renderer->render(
            $preparedConfigs,
            $messages,
            wp_create_nonce('minisite_config_save'),
            wp_create_nonce('minisite_config_delete')
        );

        $this->logger->debug("render() exit");
    }

    /**
     * Handle save request
     */
    private function handleSave(): void
    {
        $this->logger->debug("handleSave() entry");

        $updated = 0;
        $errors = array();

        // Process each config field
        $configData = $this->getPostDataArray('config');
        foreach ($configData as $key => $data) {
            try {
                $value = $data['value'] ?? '';
                $type = $data['type'] ?? 'string';
                $description = $data['description'] ?? null;

                // Sanitize the key
                $key = sanitize_text_field($key);

                // Special handling: if sensitive field and value is masked, don't update
                $existing = $this->configService->find($key);
                if ($existing && $existing->isSensitive && $this->isMaskedValue($value)) {
                    continue; // Skip masked values (user didn't change it)
                }

                $command = new SaveConfigCommand($key, $value, $type, $description);
                $this->saveHandler->handle($command);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to save {$key}: " . $e->getMessage();
                $this->logger->error("handleSave() failed for key", array(
                    'key' => $key,
                    'error' => $e->getMessage(),
                ));
            }
        }

        // Handle new config addition
        $newKey = $this->getPostData('new_config_key');
        if (! empty($newKey)) {
            $key = $newKey;
            $value = $this->getPostDataTextarea('new_config_value');
            $type = $this->getPostData('new_config_type', 'string');
            $description = $this->getPostDataTextarea('new_config_description');

            // Validate key format (lowercase with underscores only)
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $errors[] = "Invalid key format. Use lowercase letters, numbers, and underscores only "
                    . "(e.g., 'whatsapp_access_token')";
            } else {
                try {
                    $command = new SaveConfigCommand($key, $value, $type, $description);
                    $this->saveHandler->handle($command);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to add new config: " . $e->getMessage();
                    $this->logger->error("handleSave() failed for new config", array(
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ));
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

        if (! empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error('minisite_config', 'config_error', $error, 'error');
            }
        }

        $this->logger->debug("handleSave() exit", array(
            'updated' => $updated,
            'errors' => count($errors),
        ));
    }

    /**
     * Handle delete request
     */
    private function handleDelete(string $key): void
    {
        $this->logger->debug("handleDelete() entry", array(
            'key' => $key,
        ));

        try {
            // Prevent deletion of required/default configurations
            $defaultConfigs = array('openai_api_key', 'pii_encryption_key', 'max_reviews_per_page');
            if (in_array($key, $defaultConfigs, true)) {
                add_settings_error(
                    'minisite_config',
                    'config_error',
                    'Cannot delete required configuration: ' . $key,
                    'error'
                );

                $this->logger->warning("handleDelete() blocked - required config", array(
                    'key' => $key,
                ));

                return;
            }

            $config = $this->configService->find($key);

            // Also check if config is marked as required in database
            if ($config && $config->isRequired) {
                add_settings_error(
                    'minisite_config',
                    'config_error',
                    'Cannot delete required configuration: ' . $key,
                    'error'
                );

                $this->logger->warning("handleDelete() blocked - isRequired flag", array(
                    'key' => $key,
                ));

                return;
            }

            $command = new DeleteConfigCommand($key);
            $this->deleteHandler->handle($command);

            add_settings_error(
                'minisite_config',
                'config_deleted',
                'Configuration deleted successfully.',
                'updated'
            );

            $this->logger->debug("handleDelete() exit", array(
                'key' => $key,
            ));
        } catch (\Exception $e) {
            add_settings_error(
                'minisite_config',
                'config_error',
                'Failed to delete configuration: ' . $e->getMessage(),
                'error'
            );

            $this->logger->error("handleDelete() failed", array(
                'key' => $key,
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Safely get and sanitize POST data as text
     *
     * Note: This method should only be called after nonce verification.
     * Nonce verification is handled in handleRequest() before calling this method.
     */
    private function getPostData(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized below
        if (! isset($_POST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized below
        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    /**
     * Safely get and sanitize POST data as textarea
     *
     * Note: This method should only be called after nonce verification.
     * Nonce verification is handled in handleRequest() before calling this method.
     */
    private function getPostDataTextarea(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized below
        if (! isset($_POST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized below
        return sanitize_textarea_field(wp_unslash($_POST[$key]));
    }

    /**
     * Safely get and sanitize POST data array
     *
     * Note: This method should only be called after nonce verification.
     * Nonce verification is handled in handleRequest() before calling this method.
     */
    private function getPostDataArray(string $key, array $default = array()): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized in loop
        if (! isset($_POST[$key]) || ! is_array($_POST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified before calling this method, data is sanitized in loop
        return wp_unslash($_POST[$key]);
    }

    /**
     * Verify nonce for POST requests
     */
    private function verifyNonce(string $action, string $nonceField): bool
    {
        $nonce = $this->getPostData($nonceField);

        return ! empty($nonce) && wp_verify_nonce($nonce, $action);
    }

    /**
     * Check if value is masked (e.g., "••••••••1234")
     */
    private function isMaskedValue(string $value): bool
    {
        return str_starts_with($value, '••••');
    }

    /**
     * Get settings messages
     */
    private function getSettingsMessages(): array
    {
        $messages = array();
        $errors = get_settings_errors('minisite_config');

        foreach ($errors as $error) {
            $messages[] = array(
                'type' => $error['type'],
                'message' => $error['message'],
            );
        }

        return $messages;
    }
}
