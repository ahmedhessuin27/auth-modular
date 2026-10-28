<?php

namespace App\Modules\Auth\Presentation\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Emails are stored lowercase, so "Ahmed@Example.com" reaches the same
 * account as "ahmed@example.com".
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }
}
