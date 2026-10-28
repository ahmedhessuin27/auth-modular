<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Auth\Domain\Enums\SocialProvider;

final readonly class SocialLoginData
{
    /**
     * @param  ?string  $name  Sent by the app: Apple only shares the user's name with it, and only on the first sign-in.
     * @param  string  $deviceName  Name stored on the issued API token.
     */
    public function __construct(
        public SocialProvider $provider,
        public string $idToken,
        public ?string $name,
        public string $deviceName,
    ) {}
}
