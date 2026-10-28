<?php

namespace App\Modules\Users\Application\Services;

use App\Models\User;
use App\Modules\Auth\Application\Services\AuthEventPublisher;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Users\Application\DTOs\AuthenticatedUser;
use App\Modules\Users\Application\DTOs\LoginData;
use App\Modules\Users\Application\DTOs\RegisterUserData;
use App\Modules\Users\Domain\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserAuthService
{
    public function __construct(
        private readonly AuthEventPublisher $eventPublisher,
    ) {}

    /**
     * Creates the user and stores its UserRegistered event in the outbox
     * within one transaction, so the event is only relayed to RabbitMQ
     * once the user actually exists.
     */
    public function register(RegisterUserData $data): AuthenticatedUser
    {
        return DB::transaction(function () use ($data): AuthenticatedUser {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

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
     */
    public function login(LoginData $data): AuthenticatedUser
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
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
