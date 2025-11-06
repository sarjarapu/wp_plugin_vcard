<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Controllers;

use Minisite\Features\ConfigurationManagement\Controllers\ConfigurationManagementController;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler;
use Minisite\Features\ConfigurationManagement\Rendering\ConfigurationManagementRenderer;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ConfigurationManagementController
 */
#[CoversClass(ConfigurationManagementController::class)]
final class ConfigurationManagementControllerTest extends TestCase
{
    private SaveConfigHandler|MockObject $saveHandler;
    private DeleteConfigHandler|MockObject $deleteHandler;
    private ConfigurationManagementService|MockObject $configService;
    private ConfigurationManagementRenderer|MockObject $renderer;
    private ConfigurationManagementController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->saveHandler = $this->createMock(SaveConfigHandler::class);
        $this->deleteHandler = $this->createMock(DeleteConfigHandler::class);
        $this->configService = $this->createMock(ConfigurationManagementService::class);
        $this->renderer = $this->createMock(ConfigurationManagementRenderer::class);

        $this->controller = new ConfigurationManagementController(
            $this->saveHandler,
            $this->deleteHandler,
            $this->configService,
            $this->renderer
        );

        // Clear $_POST and $_SERVER
        $_POST = array();
        $_SERVER = array();
    }

    protected function tearDown(): void
    {
        $_POST = array();
        $_SERVER = array();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementController::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(4, $params);
    }

    /**
     * Test handleRequest does nothing for GET requests
     */
    public function test_handleRequest_does_nothing_for_get(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->saveHandler
            ->expects($this->never())
            ->method('handle');

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest verifies nonce for POST
     */
    public function test_handleRequest_verifies_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'invalid_nonce';

        \Brain\Monkey\Functions\expect('wp_die')
            ->once()
            ->with('Security check failed');

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest handles save action
     */
    public function test_handleRequest_handles_save_action(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'valid_nonce';
        $_POST['action'] = 'save';
        $_POST['config'] = array(
            'test_key' => array(
                'value' => 'test_value',
                'type' => 'string',
                'description' => 'Test description'
            )
        );

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'minisite_config_save')
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_settings_error')
            ->once();

        $this->configService
            ->expects($this->once())
            ->method('find')
            ->with('test_key')
            ->willReturn(null);

        $this->saveHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($command) {
                return $command->key === 'test_key'
                    && $command->value === 'test_value'
                    && $command->type === 'string';
            }));

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest handles delete action
     */
    public function test_handleRequest_handles_delete_action(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'valid_nonce';
        $_POST['action'] = 'delete';
        $_POST['config_key'] = 'test_key';

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'minisite_config_save')
            ->andReturn(true);

        $config = $this->createMock(Config::class);
        $config->isRequired = false;
        $config->key = 'test_key';

        $this->configService
            ->expects($this->once())
            ->method('find')
            ->with('test_key')
            ->willReturn($config);

        \Brain\Monkey\Functions\expect('add_settings_error')
            ->once();

        $this->deleteHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($command) {
                return $command->key === 'test_key';
            }));

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest prevents deletion of required configs
     */
    public function test_handleRequest_prevents_deletion_of_required_configs(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'valid_nonce';
        $_POST['action'] = 'delete';
        $_POST['config_key'] = 'openai_api_key';

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_settings_error')
            ->once()
            ->with(
                'minisite_config',
                'config_error',
                'Cannot delete required configuration: openai_api_key',
                'error'
            );

        $this->deleteHandler
            ->expects($this->never())
            ->method('handle');

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest handles new config addition
     */
    public function test_handleRequest_handles_new_config(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'valid_nonce';
        $_POST['action'] = 'save';
        $_POST['new_config_key'] = 'new_key';
        $_POST['new_config_value'] = 'new_value';
        $_POST['new_config_type'] = 'string';
        $_POST['new_config_description'] = 'New description';

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_settings_error')
            ->once();

        $this->saveHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($command) {
                return $command->key === 'new_key'
                    && $command->value === 'new_value'
                    && $command->type === 'string';
            }));

        $this->controller->handleRequest();
    }

    /**
     * Test handleRequest validates key format for new config
     */
    public function test_handleRequest_validates_key_format(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_config_nonce'] = 'valid_nonce';
        $_POST['action'] = 'save';
        $_POST['new_config_key'] = 'Invalid-Key-Name'; // Invalid format

        \Brain\Monkey\Functions\expect('wp_verify_nonce')
            ->once()
            ->andReturn(true);

        \Brain\Monkey\Functions\expect('add_settings_error')
            ->once()
            ->with(
                'minisite_config',
                'config_error',
                $this->stringContains('Invalid key format'),
                'error'
            );

        $this->saveHandler
            ->expects($this->never())
            ->method('handle');

        $this->controller->handleRequest();
    }

    /**
     * Test render calls renderer
     */
    public function test_render_calls_renderer(): void
    {
        $config1 = $this->createMock(Config::class);
        $config2 = $this->createMock(Config::class);

        $this->configService
            ->expects($this->once())
            ->method('all')
            ->with(true)
            ->willReturn([$config1, $config2]);

        $this->renderer
            ->expects($this->once())
            ->method('prepareConfigForTemplate')
            ->willReturn(['key' => 'test']);

        \Brain\Monkey\Functions\expect('wp_create_nonce')
            ->twice()
            ->andReturn('test_nonce');

        $this->renderer
            ->expects($this->once())
            ->method('render')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                'test_nonce',
                'test_nonce'
            );

        $this->controller->render();
    }

    /**
     * Test class is not final (removed to allow mocking in tests)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementController::class);
        $this->assertFalse($reflection->isFinal());
    }
}

