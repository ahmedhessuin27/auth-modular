<?php

namespace App\Modules\Users\Application\DTOs;

final readonly class RegisterUserData
{
    /**
     * @param  string  $deviceName  Name stored on the issued API token.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}
}
