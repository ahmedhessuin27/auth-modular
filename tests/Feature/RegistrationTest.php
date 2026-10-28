<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use App\Modules\Auth\IntegrationEvents\VerificationCodeIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_emails_a_code_without_creating_the_user(): void
    {
        $response = $this->register();

        $response->assertOk()
            ->assertJsonPath('data.code_sent', true)
            ->assertJsonPath('data.resends_left', 3)
            ->assertJsonPath('data.retry_after', 60);
        $this->assertNotEmpty($response->json('data.verification_token'));

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('outbox_messages', [
            'name' => VerificationCodeIssued::NAME,
            'payload->email' => 'ahmed@example.com',
            'payload->purpose' => 'registration',
        ]);
    }

    public function test_verifying_the_code_creates_the_verified_user_and_signs_them_in(): void
    {
        $verificationToken = $this->register()->json('data.verification_token');

        $response = $this->verify($verificationToken, $this->lastVerificationCode(), [
            'device_name' => 'iphone',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'ahmed@example.com')
            ->assertJsonPath('data.token_type', 'Bearer');
        $this->assertNotEmpty($response->json('data.token'));

        $user = User::query()->where('email', 'ahmed@example.com')->sole();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('iphone', $user->tokens()->sole()->name);
        $this->assertDatabaseHas('outbox_messages', [
            'name' => UserRegistered::NAME,
            'payload->user_id' => (string) $user->id,
        ]);
    }

    public function test_emails_are_stored_lowercase(): void
    {
        $verificationToken = $this->register(['email' => 'Ahmed@Example.com'])->json('data.verification_token');

        $this->verify($verificationToken, $this->lastVerificationCode(), [
            'email' => 'AHMED@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ahmed@example.com']);
    }

    public function test_four_wrong_codes_lock_verification_for_five_minutes(): void
    {
        $verificationToken = $this->register()->json('data.verification_token');

        foreach (range(1, 3) as $attempt) {
            $this->verify($verificationToken, '0000')
                ->assertJsonValidationErrors(['code' => 'The code is invalid or has expired.']);
        }

        $this->verify($verificationToken, '0000')
            ->assertJsonValidationErrors(['code' => 'Too many incorrect codes. Try again in 5 minutes.']);

        // Even the right code is refused while locked.
        $this->verify($verificationToken, $this->lastVerificationCode())->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_codes_expire_after_five_minutes(): void
    {
        $verificationToken = $this->register()->json('data.verification_token');

        $this->travel(5)->minutes();

        $this->verify($verificationToken, $this->lastVerificationCode())
            ->assertJsonValidationErrors(['code' => 'The code is invalid or has expired.']);
    }

    public function test_registering_again_invalidates_the_earlier_verification_token(): void
    {
        // Someone else registers the email first, with their own password...
        $earlierToken = $this->register(['password' => 'attacker-password', 'password_confirmation' => 'attacker-password'])
            ->json('data.verification_token');

        // ...then the owner registers and enters the code from their inbox.
        $ownerToken = $this->register()->json('data.verification_token');

        $this->verify($earlierToken, $this->lastVerificationCode())
            ->assertJsonValidationErrors('verification_token');

        $this->verify($ownerToken, $this->lastVerificationCode())->assertCreated();

        $user = User::query()->where('email', 'ahmed@example.com')->sole();
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_a_new_code_can_only_be_requested_once_a_minute(): void
    {
        $verificationToken = $this->register()->json('data.verification_token');

        $this->resend($verificationToken)
            ->assertOk()
            ->assertJsonPath('data.code_sent', false)
            ->assertJsonPath('data.retry_after', 60)
            ->assertJsonMissingPath('data.verification_token');

        $this->travel(1)->minute();

        $this->resend($verificationToken)
            ->assertOk()
            ->assertJsonPath('data.code_sent', true)
            ->assertJsonPath('data.resends_left', 2);

        $this->assertDatabaseCount('outbox_messages', 2);
    }

    public function test_at_most_four_codes_are_sent_per_fifteen_minutes(): void
    {
        $verificationToken = $this->register()->json('data.verification_token');

        foreach (range(1, 3) as $resend) {
            $this->travel(1)->minute();
            $this->resend($verificationToken)->assertJsonPath('data.code_sent', true);
        }

        $this->travel(1)->minute();

        $this->resend($verificationToken)
            ->assertJsonValidationErrors(['email' => 'Too many codes requested. Try again in 11 minutes.']);
    }

    public function test_registration_rejects_a_taken_email_and_unconfirmed_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->register([
            'email' => 'taken@example.com',
            'password_confirmation' => 'something-else',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('outbox_messages', 0);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson(route('api.auth.register'), [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function verify(string $verificationToken, string $code, array $overrides = []): TestResponse
    {
        return $this->postJson(route('api.auth.register.verify'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
            'code' => $code,
            ...$overrides,
        ]);
    }

    private function resend(string $verificationToken): TestResponse
    {
        return $this->postJson(route('api.auth.register.resend-code'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
        ]);
    }
}
