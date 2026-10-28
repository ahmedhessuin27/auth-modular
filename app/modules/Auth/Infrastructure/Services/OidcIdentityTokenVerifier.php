<?php

namespace App\Modules\Auth\Infrastructure\Services;

use App\Modules\Auth\Application\DTOs\SocialIdentity;
use App\Modules\Auth\Domain\Contracts\IdentityTokenVerifier;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use App\Modules\Auth\Domain\Exceptions\InvalidIdentityTokenException;
use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Google and Apple ID tokens are both OpenID Connect JWTs signed with the
 * provider's published RSA keys, so one verifier handles both.
 */
final class OidcIdentityTokenVerifier implements IdentityTokenVerifier
{
    // Both providers publish new keys well before signing with them.
    private const KEYS_CACHE_SECONDS = 3600;

    public function verify(SocialProvider $provider, string $idToken): SocialIdentity
    {
        $clientIds = config("services.{$provider->value}.client_ids");

        if (empty($clientIds)) {
            throw new RuntimeException("No client IDs are configured for {$provider->value} sign-in.");
        }

        // Fetched outside the try: the provider being down is our failure,
        // not an invalid token.
        $keys = $this->keys($provider);

        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $exception) {
            throw new InvalidIdentityTokenException($exception->getMessage(), previous: $exception);
        }

        // JWT::decode only checks expiry when the claim is present.
        if (! isset($claims['exp'])) {
            throw new InvalidIdentityTokenException('The token has no expiry.');
        }

        if (! in_array($claims['iss'] ?? null, $this->issuers($provider), true)) {
            throw new InvalidIdentityTokenException('The token was issued by someone else.');
        }

        if (array_intersect((array) ($claims['aud'] ?? []), $clientIds) === []) {
            throw new InvalidIdentityTokenException('The token was issued for another app.');
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new InvalidIdentityTokenException('The token does not identify a user.');
        }

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $claims['sub'],
            email: isset($claims['email']) ? Str::lower($claims['email']) : null,
            // Apple sends it as the string "true".
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            name: $claims['name'] ?? null,
        );
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    private function keys(SocialProvider $provider): array
    {
        return Cache::remember(
            "auth.identity_keys.{$provider->value}",
            self::KEYS_CACHE_SECONDS,
            fn (): array => Http::timeout(5)->get($this->keysUrl($provider))->throw()->json(),
        );
    }

    private function keysUrl(SocialProvider $provider): string
    {
        return match ($provider) {
            SocialProvider::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            SocialProvider::Apple => 'https://appleid.apple.com/auth/keys',
        };
    }

    /**
     * @return list<string>
     */
    private function issuers(SocialProvider $provider): array
    {
        return match ($provider) {
            SocialProvider::Google => ['https://accounts.google.com', 'accounts.google.com'],
            SocialProvider::Apple => ['https://appleid.apple.com'],
        };
    }
}
