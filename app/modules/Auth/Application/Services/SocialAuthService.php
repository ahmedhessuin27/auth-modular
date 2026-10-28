<?php

namespace App\Modules\Auth\Application\Services;

use App\Models\User;
use App\Modules\Auth\Application\DTOs\AuthenticatedUser;
use App\Modules\Auth\Application\DTOs\SocialIdentity;
use App\Modules\Auth\Application\DTOs\SocialLoginData;
use App\Modules\Auth\Domain\Contracts\IdentityTokenVerifier;
use App\Modules\Auth\Domain\Exceptions\InvalidIdentityTokenException;
use App\Modules\Auth\Infrastructure\Models\SocialAccount;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SocialAuthService
{
    public function __construct(
        private readonly IdentityTokenVerifier $identityTokens,
        private readonly AuthEventPublisher $eventPublisher,
    ) {}

    /**
     * Signs in the user linked to the Google / Apple account. The first
     * time, the account is linked to the user with the same email, or a
     * new user is created for it.
     *
     * @throws ValidationException
     */
    public function authenticate(SocialLoginData $data): AuthenticatedUser
    {
        try {
            $identity = $this->identityTokens->verify($data->provider, $data->idToken);
        } catch (InvalidIdentityTokenException) {
            throw ValidationException::withMessages([
                'id_token' => __('The sign-in token is invalid or has expired.'),
            ]);
        }

        return DB::transaction(function () use ($data, $identity): AuthenticatedUser {
            $user = $this->linkedUser($identity) ?? $this->linkUser($identity, $data->name);

            return new AuthenticatedUser(
                user: $user,
                token: $user->createToken($data->deviceName)->plainTextToken,
            );
        });
    }

    private function linkedUser(SocialIdentity $identity): ?User
    {
        return SocialAccount::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->providerUserId)
            ->first()
            ?->user;
    }

    /**
     * @throws ValidationException
     */
    private function linkUser(SocialIdentity $identity, ?string $name): User
    {
        // Linking by an email the provider hasn't verified would hand the
        // matching account to whoever typed that address into Google.
        if ($identity->email === null || ! $identity->emailVerified) {
            throw ValidationException::withMessages([
                'id_token' => __('Your :provider account has no verified email address.', [
                    'provider' => Str::headline($identity->provider->value),
                ]),
            ]);
        }

        $user = User::query()->where('email', $identity->email)->first();

        if ($user === null) {
            $user = $this->createUser($identity, $name);
        } elseif ($user->email_verified_at === null) {
            // Created before registration required a code, so whoever set
            // its password never proved they own this email. Lock them out.
            $user->password = Str::random(40);
            $user->email_verified_at = now();
            $user->save();

            $user->tokens()->delete();
        }

        SocialAccount::query()->create([
            'user_id' => $user->getKey(),
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
        ]);

        return $user;
    }

    private function createUser(SocialIdentity $identity, ?string $name): User
    {
        $user = new User([
            'name' => $identity->name ?? $name ?? Str::before($identity->email, '@'),
            'email' => $identity->email,
            // Never shown to anyone; the user can set a real one through
            // forgot password.
            'password' => Str::random(40),
        ]);
        $user->email_verified_at = now();
        $user->save();

        $this->eventPublisher->userRegistered(new UserRegistered(
            userId: (string) $user->getKey(),
            email: $user->email,
            name: $user->name,
        ));

        return $user;
    }
}
