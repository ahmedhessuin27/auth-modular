<?php

namespace App\Modules\Auth\IntegrationEvents;

/**
 * Published by Auth when a user registers. IntegrationEvents is Auth's
 * public contract: other modules may depend on it, never on Auth's Domain.
 */
final readonly class UserRegistered
{
    public const NAME = 'auth.user.registered';

    public function __construct(
        public string $userId,
        public string $email,
        public string $name,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            userId: $payload['user_id'],
            email: $payload['email'],
            name: $payload['name'],
        );
    }
}
