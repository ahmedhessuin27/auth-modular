<?php

namespace App\Modules\Auth\Application\DTOs;

final readonly class VerifyCodeData
{
    /**
     * @param  string  $verificationToken  Returned when the code was requested; ties the code to that request.
     * @param  string  $deviceName  Name stored on the API token, for verifications that sign the user in.
     */
    public function __construct(
        public string $email,
        public string $verificationToken,
        public string $code,
        public string $deviceName,
    ) {}
}
