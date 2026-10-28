<?php

namespace App\Modules\Users\Application\DTOs;

final readonly class LoginData
{
    /**
     * @param  string  $deviceName  Name stored on the issued API token.
     */
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}
}
