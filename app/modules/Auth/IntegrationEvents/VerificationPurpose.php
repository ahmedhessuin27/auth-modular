<?php

namespace App\Modules\Auth\IntegrationEvents;

/**
 * Why a verification code was sent. Part of Auth's public contract, as it
 * travels inside VerificationCodeIssued.
 */
enum VerificationPurpose: string
{
    case Registration = 'registration';
    case PasswordReset = 'password_reset';
}
