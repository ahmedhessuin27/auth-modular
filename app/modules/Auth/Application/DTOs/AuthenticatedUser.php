<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Models\User;

/**
 * Result of a successful sign-in: the user plus the plain-text API token,
 * which is only available at the moment it is issued.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
