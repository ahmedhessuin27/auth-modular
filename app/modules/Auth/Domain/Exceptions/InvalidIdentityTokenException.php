<?php

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

/**
 * A social sign-in ID token failed verification: bad signature, expired,
 * or issued by / for someone else.
 */
final class InvalidIdentityTokenException extends RuntimeException {}
