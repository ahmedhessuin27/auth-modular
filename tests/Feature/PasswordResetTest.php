<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\IntegrationEvents\VerificationCodeIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_code_to_an_existing_user(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com']);

        $this->forgotPassword()
            ->assertOk()
            ->assertJsonPath('data.code_sent', true);

        $this->assertDatabaseHas('outbox_messages', [
            'name' => VerificationCodeIssued::NAME,
            'payload->email' => 'ahmed@example.com',
            'payload->purpose' => 'password_reset',
        ]);
    }

    public function test_forgot_password_rejects_an_unknown_email(): void
    {
        $this->forgotPassword()
            ->assertJsonValidationErrors(['email' => __('passwords.user')]);

        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_user_can_reset_their_password_after_verifying_the_code(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com']);
        $user->createToken('old-phone');

        $verificationToken = $this->forgotPassword()->json('data.verification_token');

        $this->postJson(route('api.auth.forgot-password.verify'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
            'code' => $this->lastVerificationCode(),
        ])->assertNoContent();

        $this->resetPassword($verificationToken)->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        // Signed out everywhere, in case the old password leaked.
        $this->assertSame(0, $user->tokens()->count());

        // The reset can't be replayed.
        $this->resetPassword($verificationToken)->assertJsonValidationErrors('verification_token');
    }

    public function test_password_cannot_be_reset_before_the_code_is_verified(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com']);

        $verificationToken = $this->forgotPassword()->json('data.verification_token');

        $this->resetPassword($verificationToken)
            ->assertJsonValidationErrors(['verification_token' => __('passwords.token')]);
    }

    public function test_resetting_verifies_an_account_created_before_codes_were_required(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ahmed@example.com']);

        $verificationToken = $this->forgotPassword()->json('data.verification_token');

        $this->postJson(route('api.auth.forgot-password.verify'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
            'code' => $this->lastVerificationCode(),
        ])->assertNoContent();

        $this->resetPassword($verificationToken)->assertNoContent();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    private function forgotPassword(): TestResponse
    {
        return $this->postJson(route('api.auth.forgot-password'), [
            'email' => 'ahmed@example.com',
        ]);
    }

    private function resetPassword(string $verificationToken): TestResponse
    {
        return $this->postJson(route('api.auth.reset-password'), [
            'email' => 'ahmed@example.com',
            'verification_token' => $verificationToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);
    }
}
