<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertSame('api', $user->tokens()->sole()->name);
    }

    public function test_login_ignores_the_case_of_the_email(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com']);

        $this->postJson(route('api.auth.login'), [
            'email' => 'Ahmed@Example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_an_unknown_email(): void
    {
        $this->postJson(route('api.auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);
    }

    public function test_login_fails_for_an_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertJsonValidationErrors([
            'email' => 'Your email address is not verified. Reset your password to verify it.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
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
            ->postJson(route('api.auth.logout'))
            ->assertNoContent();

        $this->assertSame(['other-device'], $user->tokens()->pluck('name')->all());
    }

    public function test_guests_cannot_access_protected_routes(): void
    {
        $this->getJson(route('api.users.me'))->assertUnauthorized();
        $this->postJson(route('api.auth.logout'))->assertUnauthorized();

        // Requests without an Accept: application/json header still get JSON.
        $this->get(route('api.users.me'))->assertUnauthorized();
    }
}
