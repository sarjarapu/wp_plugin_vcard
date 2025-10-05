<?php

namespace Minisite\Features\VersionManagement\Commands;

use PHPUnit\Framework\TestCase;

/**
 * Test for PublishVersionCommand
 */
class PublishVersionCommandTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $siteId = 'test-site-123';
        $versionId = 789;
        $userId = 456;

        $command = new PublishVersionCommand($siteId, $versionId, $userId);

        $this->assertEquals($siteId, $command->siteId);
        $this->assertEquals($versionId, $command->versionId);
        $this->assertEquals($userId, $command->userId);
    }

    public function test_properties_are_public_readonly(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);

        // Test that properties are accessible and have correct values
        $this->assertEquals('test-site', $command->siteId);
        $this->assertEquals(123, $command->versionId);
        $this->assertEquals(456, $command->userId);
    }
}
