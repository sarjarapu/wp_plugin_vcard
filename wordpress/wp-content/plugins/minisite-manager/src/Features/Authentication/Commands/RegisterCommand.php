<?php

namespace Minisite\Features\Authentication\Commands;

/**
 * Register Command
 *
 * Represents a user registration request with all necessary data.
 */
final class RegisterCommand
{
    public function __construct(
        public readonly string $userLogin,
        public readonly string $userEmail,
        public readonly string $userPassword,
        public readonly string $redirectTo
    ) {
    }
}
