<?php

namespace App\Modules\Auth\Domain\Events;

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
}
