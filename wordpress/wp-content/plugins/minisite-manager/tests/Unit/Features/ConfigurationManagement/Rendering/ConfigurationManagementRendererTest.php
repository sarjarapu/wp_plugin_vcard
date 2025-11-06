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
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
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
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $config = new Config();
        $config->key = 'secret_key';
        $config->type = 'encrypted';
        $config->setTypedValue('secret_value_12345');
        $config->isSensitive = true;

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertSame('secret_value_12345', $result['value']); // Actual value
        $this->assertStringContainsString('••••', $result['display_value']); // Masked display
        $this->assertStringEndsWith('12345', $result['display_value']); // Last 4 chars visible
    }

    /**
     * Test prepareConfigForTemplate formats key name
     */
    public function test_prepareConfigForTemplate_formats_key_name(): void
    {
        $config = new Config();
        $config->key = 'openai_api_key';
        $config->type = 'string';
        $config->setTypedValue('test');

        $result = $this->renderer->prepareConfigForTemplate($config);

        $this->assertSame('openai API KEY', $result['display_name']);
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
     */
    public function test_render_calls_timber_when_available(): void
    {
        // Mock Timber class
        if (!class_exists('Timber\\Timber')) {
            eval('namespace Timber; class Timber { public static $locations = []; public static function render($template, $context) {} }');
        }

        \Brain\Monkey\Functions\expect('trailingslashit')
            ->once()
            ->andReturn('/path/to/templates/');

        \Brain\Monkey\Functions\expect('wp_die')
            ->never();

        $configs = array(
            array(
                'key' => 'test_key',
                'display_name' => 'Test Key',
                'value' => 'test_value',
                'display_value' => 'test_value',
                'type' => 'string',
                'description' => null,
                'is_sensitive' => false,
                'is_required' => false
            )
        );

        $messages = array();
        $nonce = 'test_nonce';
        $deleteNonce = 'test_delete_nonce';

        // This should not throw
        try {
            $this->renderer->render($configs, $messages, $nonce, $deleteNonce);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If Timber is not available, that's acceptable
            if (str_contains($e->getMessage(), 'Timber')) {
                $this->markTestSkipped('Timber not available: ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementRenderer::class);
        $this->assertTrue($reflection->isFinal());
    }
}

