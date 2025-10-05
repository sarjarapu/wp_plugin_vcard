<?php

namespace Tests\Unit\Features\Authentication\Commands;

use Minisite\Features\Authentication\Commands\RegisterCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test RegisterCommand
 * 
 * Tests the RegisterCommand value object to ensure proper data encapsulation
 */
final class RegisterCommandTest extends TestCase
{
    /**
     * Test RegisterCommand constructor with all parameters
     */
    public function test_constructor_sets_all_properties(): void
    {
        $userLogin = 'newuser';
        $userEmail = 'newuser@example.com';
        $userPassword = 'newpass123';
        $redirectTo = '/account/dashboard';

        $command = new RegisterCommand($userLogin, $userEmail, $userPassword, $redirectTo);

        $this->assertEquals($userLogin, $command->userLogin);
        $this->assertEquals($userEmail, $command->userEmail);
        $this->assertEquals($userPassword, $command->userPassword);
        $this->assertEquals($redirectTo, $command->redirectTo);
    }

    /**
     * Test RegisterCommand with valid email formats
     */
    public function test_constructor_with_valid_email_formats(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'user123@subdomain.example.com',
            'user@example-domain.com'
        ];

        foreach ($validEmails as $email) {
            $command = new RegisterCommand('user', $email, 'pass123', '/redirect');
            $this->assertEquals($email, $command->userEmail);
        }
    }

    /**
     * Test RegisterCommand with empty strings
     */
    public function test_constructor_with_empty_strings(): void
    {
        $command = new RegisterCommand('', '', '', '');

        $this->assertEquals('', $command->userLogin);
        $this->assertEquals('', $command->userEmail);
        $this->assertEquals('', $command->userPassword);
        $this->assertEquals('', $command->redirectTo);
    }

    /**
     * Test RegisterCommand with special characters in username
     */
    public function test_constructor_with_special_characters_in_username(): void
    {
        $userLogin = 'user.name_123';
        $command = new RegisterCommand($userLogin, 'email@test.com', 'pass123', '/redirect');

        $this->assertEquals($userLogin, $command->userLogin);
    }

    /**
     * Test RegisterCommand with complex redirect URL
     */
    public function test_constructor_with_complex_redirect_url(): void
    {
        $redirectTo = '/account/dashboard?param=value&other=test#section';
        $command = new RegisterCommand('user', 'email@test.com', 'pass123', $redirectTo);

        $this->assertEquals($redirectTo, $command->redirectTo);
    }

    /**
     * Test RegisterCommand properties are readonly
     */
    public function test_properties_are_readonly(): void
    {
        $command = new RegisterCommand('user', 'email@test.com', 'pass123', '/redirect');

        $this->assertObjectHasProperty('userLogin', $command);
        $this->assertObjectHasProperty('userEmail', $command);
        $this->assertObjectHasProperty('userPassword', $command);
        $this->assertObjectHasProperty('redirectTo', $command);
    }
}
