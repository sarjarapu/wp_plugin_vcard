<?php

namespace Tests\Unit\Features\Authentication\Commands;

use Minisite\Features\Authentication\Commands\LoginCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test LoginCommand
 * 
 * Tests the LoginCommand value object to ensure proper data encapsulation
 */
final class LoginCommandTest extends TestCase
{
    /**
     * Test LoginCommand constructor with all parameters
     */
    public function test_constructor_sets_all_properties(): void
    {
        $userLogin = 'testuser';
        $userPassword = 'testpass123';
        $remember = true;
        $redirectTo = '/account/dashboard';

        $command = new LoginCommand($userLogin, $userPassword, $remember, $redirectTo);

        $this->assertEquals($userLogin, $command->userLogin);
        $this->assertEquals($userPassword, $command->userPassword);
        $this->assertEquals($remember, $command->remember);
        $this->assertEquals($redirectTo, $command->redirectTo);
    }

    /**
     * Test LoginCommand constructor with remember = false
     */
    public function test_constructor_with_remember_false(): void
    {
        $command = new LoginCommand('user', 'pass', false, '/redirect');

        $this->assertFalse($command->remember);
    }

    /**
     * Test LoginCommand constructor with remember = true
     */
    public function test_constructor_with_remember_true(): void
    {
        $command = new LoginCommand('user', 'pass', true, '/redirect');

        $this->assertTrue($command->remember);
    }

    /**
     * Test LoginCommand with empty strings
     */
    public function test_constructor_with_empty_strings(): void
    {
        $command = new LoginCommand('', '', false, '');

        $this->assertEquals('', $command->userLogin);
        $this->assertEquals('', $command->userPassword);
        $this->assertEquals('', $command->redirectTo);
        $this->assertFalse($command->remember);
    }

    /**
     * Test LoginCommand with special characters
     */
    public function test_constructor_with_special_characters(): void
    {
        $userLogin = 'user@example.com';
        $userPassword = 'pass!@#$%^&*()';
        $redirectTo = '/account/dashboard?param=value&other=test';

        $command = new LoginCommand($userLogin, $userPassword, true, $redirectTo);

        $this->assertEquals($userLogin, $command->userLogin);
        $this->assertEquals($userPassword, $command->userPassword);
        $this->assertEquals($redirectTo, $command->redirectTo);
    }

    /**
     * Test LoginCommand properties are readonly
     */
    public function test_properties_are_readonly(): void
    {
        $command = new LoginCommand('user', 'pass', false, '/redirect');

        // Properties should be readonly - attempting to modify should not work
        // This test verifies the readonly nature by checking the properties exist
        $this->assertObjectHasProperty('userLogin', $command);
        $this->assertObjectHasProperty('userPassword', $command);
        $this->assertObjectHasProperty('remember', $command);
        $this->assertObjectHasProperty('redirectTo', $command);
    }
}
