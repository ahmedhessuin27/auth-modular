<?php

namespace App\Modules\Auth\Application\Services;

use App\Models\User;
use App\Modules\Auth\Application\DTOs\AuthenticatedUser;
use App\Modules\Auth\Application\DTOs\LoginData;
use App\Modules\Auth\Application\DTOs\RegisterUserData;
use App\Modules\Auth\Application\DTOs\VerificationCodeStatus;
use App\Modules\Auth\Application\DTOs\VerifyCodeData;
use App\Modules\Auth\Domain\Exceptions\EmailNotVerifiedException;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Auth\IntegrationEvents\VerificationPurpose;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserAuthService
{
    public function __construct(
        private readonly AuthEventPublisher $eventPublisher,
        private readonly VerificationCodeService $verificationCodes,
    ) {}

    /**
     * Emails a verification code. No user exists until the code is
     * confirmed through verifyRegistration().
     *
     * @throws ValidationException when too many codes were requested
     */
    public function register(RegisterUserData $data): VerificationCodeStatus
    {
        return $this->verificationCodes->start(VerificationPurpose::Registration, $data->email, [
            'name' => $data->name,
            // Hashed now, so the plain password never sits in the cache.
            'password' => Hash::make($data->password),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function resendRegistrationCode(string $email, string $verificationToken): VerificationCodeStatus
    {
        return $this->verificationCodes->resend(VerificationPurpose::Registration, $email, $verificationToken);
    }

    /**
     * Creates the verified user and stores its UserRegistered event in the
     * outbox within one transaction, so the event is only relayed to
     * RabbitMQ once the user actually exists.
     *
     * @throws ValidationException
     */
    public function verifyRegistration(VerifyCodeData $data): AuthenticatedUser
    {
        $registration = $this->verificationCodes->verify(
            VerificationPurpose::Registration,
            $data->email,
            $data->verificationToken,
            $data->code,
        );

        return DB::transaction(function () use ($data, $registration): AuthenticatedUser {
            // The email was free when the code was requested, but a social
            // sign-in may have taken it since.
            if (User::query()->where('email', $data->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('validation.unique', ['attribute' => 'email']),
                ]);
            }

            $user = new User([
                'name' => $registration['name'],
                'email' => $data->email,
                'password' => $registration['password'],
            ]);
            $user->email_verified_at = now();
            $user->save();

            $this->eventPublisher->userRegistered(new UserRegistered(
                userId: (string) $user->getKey(),
                email: $user->email,
                name: $user->name,
            ));

            return new AuthenticatedUser(
                user: $user,
                token: $this->issueToken($user, $data->deviceName),
            );
        });
    }

    /**
     * @throws InvalidCredentialsException
     * @throws EmailNotVerifiedException
     */
    public function login(LoginData $data): AuthenticatedUser
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->email_verified_at === null) {
            throw new EmailNotVerifiedException;
        }

        return new AuthenticatedUser(
            user: $user,
            token: $this->issueToken($user, $data->deviceName),
        );
    }

    /**
     * Revokes only the token used for this request; the user's other
     * devices stay signed in.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
