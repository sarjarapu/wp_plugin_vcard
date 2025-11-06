<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Rendering;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Rendering\ConfigurationManagementRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurationManagementRenderer
 */
#[CoversClass(ConfigurationManagementRenderer::class)]
final class ConfigurationManagementRendererTest extends TestCase
{
    private ConfigurationManagementRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->renderer = new ConfigurationManagementRenderer();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test prepareConfigForTemplate returns correct structure
     */
    public function test_prepareConfigForTemplate_returns_correct_structure(): void
    {
        // Define encryption key for testing
        if (! defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $config = new Config();
        $config->key = 'test_key';
        $config->type = 'string';
        $config->setTypedValue('test_value');
        $config->description = 'Test description';
        $config->isSensitive = false;
        $config->isRequired = false;

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertIsArray($result);
        $this->assertSame('test_key', $result['key']);
        $this->assertSame('test_value', $result['value']);
        $this->assertSame('string', $result['type']);
        $this->assertSame('Test description', $result['description']);
        $this->assertFalse($result['is_sensitive']);
        $this->assertFalse($result['is_required']);
    }

    /**
     * Test prepareConfigForTemplate masks sensitive values
     */
    public function test_prepareConfigForTemplate_masks_sensitive_values(): void
    {
        // Define encryption key for testing
        if (! defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $originalValue = 'secret_value_12345';
        $config = new Config();
        $config->key = 'secret_key';
        $config->type = 'encrypted';
        $config->setTypedValue($originalValue);
        $config->isSensitive = true;

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertSame($originalValue, $result['value']); // Actual value
        $this->assertStringContainsString('••••', $result['display_value']); // Masked display
        // Check that last 4 characters of original value are visible
        $this->assertStringEndsWith(substr($originalValue, -4), $result['display_value']);
    }

    /**
     * Test prepareConfigForTemplate formats key name for display
     */
    public function test_prepareConfigForTemplate_formats_key_name(): void
    {
        $config = new Config();
        $config->key = 'openai_api_key';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        // Verify display_name exists and is formatted (not the raw key)
        $this->assertArrayHasKey('display_name', $result);
        $this->assertNotSame($config->key, $result['display_name'], 'display_name should be formatted, not the raw key');
        $this->assertIsString($result['display_name']);
        $this->assertNotEmpty($result['display_name']);

        // Verify it's human-readable (contains spaces, not underscores)
        $this->assertStringContainsString(' ', $result['display_name'], 'display_name should be space-separated for readability');
    }

    /**
     * Test prepareConfigForTemplate marks default configs as required
     */
    public function test_prepareConfigForTemplate_marks_default_configs_as_required(): void
    {
        $config = new Config();
        $config->key = 'openai_api_key';
        $config->type = 'string';
        $config->setTypedValue('test');
        $config->isRequired = false; // Not marked as required in DB

        $result = $this->renderer->prepareConfigForTemplate($config);

        // Should be marked as required because it's in default configs list
        $this->assertTrue($result['is_required']);
    }

    /**
     * Test render method exists
     */
    public function test_render_method_exists(): void
    {
        $this->assertTrue(method_exists($this->renderer, 'render'));
    }

    /**
     * Test render calls Timber when available
     * Note: This test is skipped because Timber requires complex setup and the real
     * Timber library has issues with test environments. The render method is tested
     * through integration tests.
     */
    public function test_render_calls_timber_when_available(): void
    {
        $this->markTestSkipped('Timber rendering is tested through integration tests. Unit test skipped due to Timber library complexity in test environment.');
    }

    /**
     * Test class is not final (removed to allow mocking in tests)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementRenderer::class);
        $this->assertFalse($reflection->isFinal());
    }
}
