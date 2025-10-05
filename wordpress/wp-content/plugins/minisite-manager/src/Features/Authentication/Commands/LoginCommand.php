<?php

namespace Minisite\Features\Authentication\Commands;

/**
 * Login Command
 * 
 * Represents a user login request with all necessary data.
 */
final class LoginCommand
{
    public function __construct(
        public readonly string $userLogin,
        public readonly string $userPassword,
        public readonly bool $remember,
        public readonly string $redirectTo
    ) {}
}