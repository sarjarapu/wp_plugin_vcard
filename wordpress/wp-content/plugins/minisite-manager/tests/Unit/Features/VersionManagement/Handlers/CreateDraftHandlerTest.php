<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Services\VersionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CreateDraftHandler
 */
class CreateDraftHandlerTest extends TestCase
{
    private CreateDraftHandler $handler;
    private MockObject $versionService;

    protected function setUp(): void
    {
        $this->versionService = $this->createMock(VersionService::class);
        $this->handler = new CreateDraftHandler($this->versionService);
    }

    public function test_handle_delegates_to_service(): void
    {
        $command = new CreateDraftCommand('test-site', 123, 'Test Version', 'Test comment', []);
        $expectedResult = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
        $expectedResult->id = 789;
        $expectedResult->versionNumber = 3;

        $this->versionService
            ->expects($this->once())
            ->method('createDraft')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->handler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }
}
