<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson(route('api.users.register'), [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'iphone',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'ahmed@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $user = User::query()->where('email', 'ahmed@example.com')->sole();

        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('iphone', $user->tokens()->sole()->name);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registration_stores_user_registered_event_in_the_outbox(): void
    {
        $this->postJson(route('api.users.register'), [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        $user = User::query()->where('email', 'ahmed@example.com')->sole();

        $this->assertDatabaseHas('outbox_messages', [
            'name' => UserRegistered::NAME,
            'published_at' => null,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'payload->user_id' => (string) $user->id,
            'payload->email' => 'ahmed@example.com',
        ]);
    }

    public function test_registration_rejects_a_taken_email_and_unconfirmed_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(route('api.users.register'), [
            'name' => 'Ahmed',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'something-else',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('api.users.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertSame('api', $user->tokens()->sole()->name);
    }

    public function test_login_fails_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.users.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_an_unknown_email(): void
    {
        $this->postJson(route('api.users.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.users.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('other-device');
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson(route('api.users.logout'))
            ->assertNoContent();

        $this->assertSame(['other-device'], $user->tokens()->pluck('name')->all());
    }

    public function test_guests_cannot_access_protected_routes(): void
    {
        $this->getJson(route('api.users.me'))->assertUnauthorized();
        $this->postJson(route('api.users.logout'))->assertUnauthorized();

        // Requests without an Accept: application/json header still get JSON.
        $this->get(route('api.users.me'))->assertUnauthorized();
    }
}
