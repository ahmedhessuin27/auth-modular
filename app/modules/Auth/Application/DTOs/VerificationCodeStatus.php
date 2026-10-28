<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * Outcome of asking for a verification code. During the resend cooldown no
 * new code is sent, and the one already sent stays valid.
 */
final readonly class VerificationCodeStatus
{
    /**
     * @param  int  $retryAfter  Seconds until another code can be requested.
     * @param  ?string  $verificationToken  Only set when a verification starts; the client sends it back to resend or verify.
     */
    public function __construct(
        public bool $codeSent,
        public int $resendsLeft,
        public int $retryAfter,
        public ?string $verificationToken = null,
    ) {}
}
