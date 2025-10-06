<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Services\VersionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for PublishVersionHandler
 */
class PublishVersionHandlerTest extends TestCase
{
    private PublishVersionHandler $handler;
    private MockObject $versionService;

    protected function setUp(): void
    {
        $this->versionService = $this->createMock(VersionService::class);
        $this->handler = new PublishVersionHandler($this->versionService);
    }

    public function test_handle_delegates_to_service(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);

        $this->versionService
            ->expects($this->once())
            ->method('publishVersion')
            ->with($command);

        $this->handler->handle($command);
    }
}
