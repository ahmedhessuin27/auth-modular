<?php

namespace App\Modules\Auth\Application\Services;

use App\Models\User;
use App\Modules\Auth\Application\DTOs\VerificationCodeStatus;
use App\Modules\Auth\Application\DTOs\VerifyCodeData;
use App\Modules\Auth\IntegrationEvents\VerificationPurpose;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Forgot password: email a code, confirm it, then set a new password with
 * the verification token the first step returned.
 */
final class PasswordResetService
{
    // How long the user has to choose a new password once the code is confirmed.
    private const RESET_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly VerificationCodeService $verificationCodes,
    ) {}

    /**
     * @throws ValidationException
     */
    public function sendCode(string $email): VerificationCodeStatus
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => __('passwords.user')]);
        }

        return $this->verificationCodes->start(VerificationPurpose::PasswordReset, $email, [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function resendCode(string $email, string $verificationToken): VerificationCodeStatus
    {
        return $this->verificationCodes->resend(VerificationPurpose::PasswordReset, $email, $verificationToken);
    }

    /**
     * Confirms the code; the same verification token then authorises reset().
     *
     * @throws ValidationException
     */
    public function verifyCode(VerifyCodeData $data): void
    {
        $reset = $this->verificationCodes->verify(
            VerificationPurpose::PasswordReset,
            $data->email,
            $data->verificationToken,
            $data->code,
        );

        Cache::put($this->resetKey($data->email), [
            'user_id' => $reset['user_id'],
            'token_hash' => hash('sha256', $data->verificationToken),
        ], now()->addMinutes(self::RESET_WINDOW_MINUTES));
    }

    /**
     * Sets the new password and signs the user out on every device. Getting
     * the code also proved they own the email, so an account created before
     * registration required a code becomes verified here.
     *
     * @throws ValidationException
     */
    public function reset(string $email, string $verificationToken, string $password): void
    {
        $reset = Cache::get($this->resetKey($email));

        $user = $reset !== null && hash_equals($reset['token_hash'], hash('sha256', $verificationToken))
            ? User::query()->find($reset['user_id'])
            : null;

        if (! $user) {
            throw ValidationException::withMessages(['verification_token' => __('passwords.token')]);
        }

        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->email_verified_at ??= now();
            $user->save();

            $user->tokens()->delete();
        });

        Cache::forget($this->resetKey($email));
    }

    private function resetKey(string $email): string
    {
        return "auth.password_reset.{$email}";
    }
}
