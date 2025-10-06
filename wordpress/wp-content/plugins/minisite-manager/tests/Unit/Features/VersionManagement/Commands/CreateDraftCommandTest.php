<?php

namespace Minisite\Features\VersionManagement\Commands;

use PHPUnit\Framework\TestCase;

/**
 * Test for CreateDraftCommand
 */
class CreateDraftCommandTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;
        $label = 'Test Version';
        $comment = 'Test comment';
        $siteJson = ['test' => 'data'];

        $command = new CreateDraftCommand($siteId, $userId, $label, $comment, $siteJson);

        $this->assertEquals($siteId, $command->siteId);
        $this->assertEquals($userId, $command->userId);
        $this->assertEquals($label, $command->label);
        $this->assertEquals($comment, $command->comment);
        $this->assertEquals($siteJson, $command->siteJson);
    }

    public function test_properties_are_public_readonly(): void
    {
        $command = new CreateDraftCommand('test-site', 123, 'label', 'comment', ['test' => 'data']);

        // Test that properties are accessible and have correct values
        $this->assertEquals('test-site', $command->siteId);
        $this->assertEquals(123, $command->userId);
        $this->assertEquals('label', $command->label);
        $this->assertEquals('comment', $command->comment);
        $this->assertEquals(['test' => 'data'], $command->siteJson);
    }
}
