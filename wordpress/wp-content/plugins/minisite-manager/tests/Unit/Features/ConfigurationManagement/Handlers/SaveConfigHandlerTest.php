<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Handlers;

use Minisite\Features\ConfigurationManagement\Commands\SaveConfigCommand;
use Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for SaveConfigHandler
 */
#[CoversClass(SaveConfigHandler::class)]
final class SaveConfigHandlerTest extends TestCase
{
    private ConfigurationManagementService|MockObject $configService;
    private SaveConfigHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->configService = $this->createMock(ConfigurationManagementService::class);
        $this->handler = new SaveConfigHandler($this->configService);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(SaveConfigHandler::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService', $params[0]->getType()->getName());
    }

    /**
     * Test handle method calls service set method
     */
    public function test_handle_calls_service_set(): void
    {
        $command = new SaveConfigCommand('test_key', 'test_value', 'string', 'Test description');

        $this->configService
            ->expects($this->once())
            ->method('set')
            ->with('test_key', 'test_value', 'string', 'Test description');

        $this->handler->handle($command);
    }

    /**
     * Test handle method with null description
     */
    public function test_handle_with_null_description(): void
    {
        $command = new SaveConfigCommand('test_key', 'test_value', 'string', null);

        $this->configService
            ->expects($this->once())
            ->method('set')
            ->with('test_key', 'test_value', 'string', null);

        $this->handler->handle($command);
    }

    /**
     * Test class is not final (removed to allow mocking in tests)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(SaveConfigHandler::class);
        $this->assertFalse($reflection->isFinal());
    }
}

