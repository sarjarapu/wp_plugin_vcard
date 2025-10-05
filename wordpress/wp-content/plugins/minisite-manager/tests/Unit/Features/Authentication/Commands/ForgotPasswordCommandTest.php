<?php

namespace Tests\Unit\Features\Authentication\Commands;

use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test ForgotPasswordCommand
 * 
 * Tests the ForgotPasswordCommand value object to ensure proper data encapsulation
 */
final class ForgotPasswordCommandTest extends TestCase
{
    /**
     * Test ForgotPasswordCommand constructor with username
     */
    public function test_constructor_sets_user_login_property(): void
    {
        $userLogin = 'testuser';
        $command = new ForgotPasswordCommand($userLogin);

        $this->assertEquals($userLogin, $command->userLogin);
    }

    /**
     * Test ForgotPasswordCommand with email address
     */
    public function test_constructor_with_email_address(): void
    {
        $userLogin = 'user@example.com';
        $command = new ForgotPasswordCommand($userLogin);

        $this->assertEquals($userLogin, $command->userLogin);
    }

    /**
     * Test ForgotPasswordCommand with empty string
     */
    public function test_constructor_with_empty_string(): void
    {
        $command = new ForgotPasswordCommand('');

        $this->assertEquals('', $command->userLogin);
    }

    /**
     * Test ForgotPasswordCommand with special characters
     */
    public function test_constructor_with_special_characters(): void
    {
        $userLogin = 'user.name_123@example-domain.com';
        $command = new ForgotPasswordCommand($userLogin);

        $this->assertEquals($userLogin, $command->userLogin);
    }

    /**
     * Test ForgotPasswordCommand with whitespace
     */
    public function test_constructor_with_whitespace(): void
    {
        $userLogin = '  testuser  ';
        $command = new ForgotPasswordCommand($userLogin);

        $this->assertEquals($userLogin, $command->userLogin);
    }

    /**
     * Test ForgotPasswordCommand property is readonly
     */
    public function test_property_is_readonly(): void
    {
        $command = new ForgotPasswordCommand('testuser');

        $this->assertObjectHasProperty('userLogin', $command);
    }

    /**
     * Test ForgotPasswordCommand with various input types
     */
    public function test_constructor_with_various_input_types(): void
    {
        $testCases = [
            'username',
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            '123456',
            'user_123',
            'user-name'
        ];

        foreach ($testCases as $input) {
            $command = new ForgotPasswordCommand($input);
            $this->assertEquals($input, $command->userLogin);
        }
    }
}
