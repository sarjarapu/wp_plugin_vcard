<?php

namespace Minisite\Features\Authentication\Commands;

/**
 * Forgot Password Command
 *
 * Represents a password reset request.
 */
final class ForgotPasswordCommand
{
    public function __construct(
        public readonly string $userLogin
    ) {
    }
}
