<?php

namespace App\Modules\Users\Domain\Exceptions;

use RuntimeException;

/**
 * The email/password pair does not match a user. Deliberately does not
 * say which part was wrong, so it cannot be used to enumerate accounts.
 */
final class InvalidCredentialsException extends RuntimeException {}
