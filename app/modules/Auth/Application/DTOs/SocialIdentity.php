<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Auth\Domain\Enums\SocialProvider;

/**
 * Who a verified Google / Apple ID token belongs to.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
