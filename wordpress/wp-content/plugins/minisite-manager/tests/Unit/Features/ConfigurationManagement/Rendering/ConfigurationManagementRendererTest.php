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

    /**
     * Test maskValue() with very short value (<= 4 chars)
     */
    public function test_maskValue_with_short_value(): void
    {
        $config = new Config();
        $config->key = 'short_key';
        $config->type = 'encrypted';
        $config->isSensitive = true;
        $config->setTypedValue('1234'); // Exactly 4 chars

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertStringStartsWith('••••', $result['display_value']);
        $this->assertEquals('••••', $result['display_value']); // Should be exactly 4 dots
    }

    /**
     * Test maskValue() with very short value (< 4 chars)
     */
    public function test_maskValue_with_very_short_value(): void
    {
        $config = new Config();
        $config->key = 'tiny_key';
        $config->type = 'encrypted';
        $config->isSensitive = true;
        $config->setTypedValue('12'); // Less than 4 chars

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertEquals('••••', $result['display_value']); // Should be exactly 4 dots
    }

    /**
     * Test maskValue() with long value (> 4 chars)
     */
    public function test_maskValue_with_long_value(): void
    {
        $config = new Config();
        $config->key = 'long_key';
        $config->type = 'encrypted';
        $config->isSensitive = true;
        $config->setTypedValue('very_long_secret_value_12345');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertStringStartsWith('••••••••', $result['display_value']);
        $this->assertStringEndsWith('2345', $result['display_value']); // Last 4 chars
        $this->assertNotEquals('very_long_secret_value_12345', $result['display_value']);
    }

    /**
     * Test formatKeyName() with various key formats
     */
    public function test_formatKeyName_with_acronyms(): void
    {
        $config = new Config();
        $config->key = 'openai_api_key';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertArrayHasKey('display_name', $result);
        $this->assertIsString($result['display_name']);
        // Should contain 'OPENAI' (uppercase acronym) and 'API' (uppercase acronym)
        $this->assertStringContainsString('OPENAI', $result['display_name']);
        $this->assertStringContainsString('API', $result['display_name']);
    }

    /**
     * Test formatKeyName() with PII acronym
     */
    public function test_formatKeyName_with_pii_acronym(): void
    {
        $config = new Config();
        $config->key = 'pii_encryption_key';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertArrayHasKey('display_name', $result);
        // Should contain 'PII' (uppercase acronym)
        $this->assertStringContainsString('PII', $result['display_name']);
    }

    /**
     * Test formatKeyName() with single word key
     */
    public function test_formatKeyName_with_single_word(): void
    {
        $config = new Config();
        $config->key = 'testkey';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertArrayHasKey('display_name', $result);
        $this->assertIsString($result['display_name']);
        $this->assertNotEmpty($result['display_name']);
    }

    /**
     * Test formatKeyName() with multiple underscores
     */
    public function test_formatKeyName_with_multiple_underscores(): void
    {
        $config = new Config();
        $config->key = 'test_key_with_many_parts';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertArrayHasKey('display_name', $result);
        $this->assertIsString($result['display_name']);
        // Should have spaces between words
        $this->assertStringContainsString(' ', $result['display_name']);
    }

    /**
     * Test registerTimberLocations() via reflection
     */
    public function test_registerTimberLocations_via_reflection(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementRenderer::class);
        $method = $reflection->getMethod('registerTimberLocations');
        $method->setAccessible(true);

        // Mock Timber class if it doesn't exist
        if (!class_exists('Timber\Timber')) {
            eval('
                namespace Timber {
                    class Timber {
                        public static $locations = array();
                    }
                }
            ');
        }

        // Call the method
        $method->invoke($this->renderer);

        // Verify Timber locations were set
        if (class_exists('Timber\Timber')) {
            $this->assertIsArray(\Timber\Timber::$locations);
            // Should contain the template path
            $expectedPath = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
            $this->assertContains($expectedPath, \Timber\Timber::$locations);
        }
    }
}
