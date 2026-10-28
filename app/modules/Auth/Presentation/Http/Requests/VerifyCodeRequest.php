<?php

namespace App\Modules\Auth\Presentation\Http\Requests;

use App\Modules\Auth\Application\DTOs\VerifyCodeData;
use App\Modules\Auth\Presentation\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
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
            'code' => ['required', 'digits:4'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function toData(): VerifyCodeData
    {
        return new VerifyCodeData(
            email: $this->validated('email'),
            verificationToken: $this->validated('verification_token'),
            code: (string) $this->validated('code'),
            deviceName: $this->validated('device_name', 'api'),
        );
    }
}
