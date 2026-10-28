<?php

namespace App\Modules\Auth\IntegrationEvents;

/**
 * Published by Auth when a verification code has to reach an email
 * address; Notifications delivers it.
 */
final readonly class VerificationCodeIssued
{
    public const NAME = 'auth.verification_code.issued';

    public function __construct(
        public string $email,
        public string $code,
        public VerificationPurpose $purpose,
        public int $expiresInMinutes,
    ) {}

    /**
     * @return array{email: string, code: string, purpose: string, expires_in_minutes: int}
     */
    public function toPayload(): array
    {
        return [
            'email' => $this->email,
            'code' => $this->code,
            'purpose' => $this->purpose->value,
            'expires_in_minutes' => $this->expiresInMinutes,
        ];
    }

    /**
     * @param  array{email: string, code: string, purpose: string, expires_in_minutes: int}  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            email: $payload['email'],
            code: $payload['code'],
            purpose: VerificationPurpose::from($payload['purpose']),
            expiresInMinutes: $payload['expires_in_minutes'],
        );
    }
}
