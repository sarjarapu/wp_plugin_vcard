<?php

namespace Minisite\Features\VersionManagement\Handlers;

use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Services\VersionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for ListVersionsHandler
 */
class ListVersionsHandlerTest extends TestCase
{
    private ListVersionsHandler $handler;
    private MockObject $versionService;

    protected function setUp(): void
    {
        $this->versionService = $this->createMock(VersionService::class);
        $this->handler = new ListVersionsHandler($this->versionService);
    }

    public function test_handle_delegates_to_service(): void
    {
        $command = new ListVersionsCommand('test-site', 123);
        $expectedResult = [
            (object) ['id' => 1, 'versionNumber' => 1],
            (object) ['id' => 2, 'versionNumber' => 2]
        ];

        $this->versionService
            ->expects($this->once())
            ->method('listVersions')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->handler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }
}
