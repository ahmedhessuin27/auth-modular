<?php

namespace App\Modules\Auth\Domain\Contracts;

use App\Modules\Auth\Application\DTOs\SocialIdentity;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use App\Modules\Auth\Domain\Exceptions\InvalidIdentityTokenException;

/**
 * Checks the ID token the mobile app received from Google / Apple sign-in
 * and returns the account it identifies.
 */
interface IdentityTokenVerifier
{
    /**
     * @throws InvalidIdentityTokenException when the token is forged, expired or issued for another app
     */
    public function verify(SocialProvider $provider, string $idToken): SocialIdentity;
}
