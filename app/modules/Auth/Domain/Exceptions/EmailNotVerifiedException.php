<?php

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

/**
 * The credentials are right but the email was never verified. Only
 * accounts created before registration required a code can be in this state.
 */
final class EmailNotVerifiedException extends RuntimeException {}
