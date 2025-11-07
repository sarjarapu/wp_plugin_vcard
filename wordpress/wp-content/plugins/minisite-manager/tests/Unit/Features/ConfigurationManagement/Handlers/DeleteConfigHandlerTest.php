<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement\Handlers;

use Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for DeleteConfigHandler
 */
#[CoversClass(DeleteConfigHandler::class)]
final class DeleteConfigHandlerTest extends TestCase
{
    private ConfigurationManagementService|MockObject $configService;
    private DeleteConfigHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        $this->configService = $this->createMock(ConfigurationManagementService::class);
        $this->handler = new DeleteConfigHandler($this->configService);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(DeleteConfigHandler::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService', $params[0]->getType()->getName());
    }

    /**
     * Test handle method calls service delete method
     */
    public function test_handle_calls_service_delete(): void
    {
        $command = new DeleteConfigCommand('test_key');

        $this->configService
            ->expects($this->once())
            ->method('delete')
            ->with('test_key');

        $this->handler->handle($command);
    }

    /**
     * Test class is not final (removed to allow mocking in tests)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(DeleteConfigHandler::class);
        $this->assertFalse($reflection->isFinal());
    }
}

