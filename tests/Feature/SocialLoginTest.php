<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use App\Modules\Auth\Infrastructure\Models\SocialAccount;
use App\Modules\Auth\IntegrationEvents\UserRegistered;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private OpenSSLAsymmetricKey $signingKey;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_ids' => ['google-client-id'],
            'services.apple.client_ids' => ['com.example.app'],
        ]);

        // Stand in for Google's and Apple's published signing keys.
        $this->signingKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $jwks = ['keys' => [$this->publicJwk($this->signingKey)]];

        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks),
            'https://appleid.apple.com/auth/keys' => Http::response($jwks),
        ]);
    }

    public function test_first_google_sign_in_creates_a_verified_user(): void
    {
        $response = $this->signIn(SocialProvider::Google, $this->googleToken());

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'ahmed@example.com')
            ->assertJsonPath('data.user.name', 'Ahmed Hessuin')
            ->assertJsonPath('data.token_type', 'Bearer');

        $user = User::query()->where('email', 'ahmed@example.com')->sole();

        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-1',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'name' => UserRegistered::NAME,
            'payload->user_id' => (string) $user->id,
        ]);
    }

    public function test_returning_user_signs_in_through_the_linked_account(): void
    {
        $account = SocialAccount::factory()->google()->create(['provider_user_id' => 'google-user-1']);

        // The Google account's email may have changed since it was linked.
        $this->signIn(SocialProvider::Google, $this->googleToken(['email' => 'new-address@example.com']))
            ->assertOk()
            ->assertJsonPath('data.user.id', $account->user_id);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_sign_in_links_the_existing_user_with_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com']);

        $this->signIn(SocialProvider::Google, $this->googleToken())
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_linking_an_unverified_account_signs_out_whoever_set_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ahmed@example.com']);
        $user->createToken('squatter');

        $this->signIn(SocialProvider::Google, $this->googleToken())->assertOk();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(password_verify('password', $user->password));
        $this->assertSame(['api'], $user->tokens()->pluck('name')->all());
    }

    public function test_apple_sign_in_uses_the_name_the_app_sends(): void
    {
        $appleToken = $this->idToken([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'sub' => 'apple-user-1',
            'email' => 'ahmed@privaterelay.appleid.com',
            'email_verified' => 'true',
        ]);

        $this->signIn(SocialProvider::Apple, $appleToken, ['name' => 'Ahmed'])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Ahmed')
            ->assertJsonPath('data.user.email', 'ahmed@privaterelay.appleid.com');
    }

    /**
     * @param  array<string, mixed>  $claimOverrides
     */
    #[DataProvider('invalidGoogleTokens')]
    public function test_tokens_that_fail_verification_are_rejected(array $claimOverrides, bool $signedByAnotherKey): void
    {
        $signingKey = $signedByAnotherKey
            ? openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048])
            : $this->signingKey;

        $idToken = JWT::encode([...$this->googleClaims(), ...$claimOverrides], $signingKey, 'RS256', 'test-key');

        $this->signIn(SocialProvider::Google, $idToken)->assertJsonValidationErrors('id_token');

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function invalidGoogleTokens(): array
    {
        return [
            'issued for another app' => [['aud' => 'someone-elses-client-id'], false],
            'issued by someone else' => [['iss' => 'https://evil.example.com'], false],
            'expired' => [['exp' => time() - 60], false],
            'without an expiry' => [['exp' => null], false],
            'forged' => [[], true],
        ];
    }

    public function test_an_unverified_provider_email_is_rejected(): void
    {
        $this->signIn(SocialProvider::Google, $this->googleToken(['email_verified' => false]))
            ->assertJsonValidationErrors(['id_token' => 'Your Google account has no verified email address.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_providers_are_not_found(): void
    {
        $this->postJson('/api/v1/auth/social/facebook', ['id_token' => 'anything'])->assertNotFound();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function signIn(SocialProvider $provider, string $idToken, array $data = []): TestResponse
    {
        return $this->postJson(route('api.auth.social', $provider), [
            'id_token' => $idToken,
            ...$data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function googleToken(array $overrides = []): string
    {
        return $this->idToken([...$this->googleClaims(), ...$overrides]);
    }

    /**
     * @return array<string, mixed>
     */
    private function googleClaims(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-client-id',
            'sub' => 'google-user-1',
            'email' => 'ahmed@example.com',
            'email_verified' => true,
            'name' => 'Ahmed Hessuin',
            'iat' => time(),
            'exp' => time() + 3600,
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $claims): string
    {
        return JWT::encode([
            'iat' => time(),
            'exp' => time() + 3600,
            ...$claims,
        ], $this->signingKey, 'RS256', 'test-key');
    }

    /**
     * @return array<string, string>
     */
    private function publicJwk(OpenSSLAsymmetricKey $key): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];

        return [
            'kty' => 'RSA',
            'kid' => 'test-key',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => JWT::urlsafeB64Encode($rsa['n']),
            'e' => JWT::urlsafeB64Encode($rsa['e']),
        ];
    }
}
