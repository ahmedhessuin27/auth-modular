<?php

namespace App\Modules\Auth\Presentation\Http\Requests;

use App\Modules\Auth\Presentation\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;

class ResendCodeRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'verification_token' => ['required', 'string'],
        ];
    }
}
