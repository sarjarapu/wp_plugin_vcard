<?php

namespace Minisite\Features\VersionManagement\Commands;

use PHPUnit\Framework\TestCase;

/**
 * Test for ListVersionsCommand
 */
class ListVersionsCommandTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;

        $command = new ListVersionsCommand($siteId, $userId);

        $this->assertEquals($siteId, $command->siteId);
        $this->assertEquals($userId, $command->userId);
    }

    public function test_properties_are_public_readonly(): void
    {
        $command = new ListVersionsCommand('test-site', 123);

        // Test that properties are accessible and have correct values
        $this->assertEquals('test-site', $command->siteId);
        $this->assertEquals(123, $command->userId);
    }
}
