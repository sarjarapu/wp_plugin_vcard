<?php

namespace Minisite\Features\VersionManagement\Commands;

use PHPUnit\Framework\TestCase;

/**
 * Test for RollbackVersionCommand
 */
class RollbackVersionCommandTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $siteId = 'test-site-123';
        $sourceVersionId = 789;
        $userId = 456;

        $command = new RollbackVersionCommand($siteId, $sourceVersionId, $userId);

        $this->assertEquals($siteId, $command->siteId);
        $this->assertEquals($sourceVersionId, $command->sourceVersionId);
        $this->assertEquals($userId, $command->userId);
    }

    public function test_properties_are_public_readonly(): void
    {
        $command = new RollbackVersionCommand('test-site', 123, 456);

        // Test that properties are accessible and have correct values
        $this->assertEquals('test-site', $command->siteId);
        $this->assertEquals(123, $command->sourceVersionId);
        $this->assertEquals(456, $command->userId);
    }
}
