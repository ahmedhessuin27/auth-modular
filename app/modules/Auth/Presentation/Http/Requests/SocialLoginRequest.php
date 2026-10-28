<?php

namespace App\Modules\Auth\Presentation\Http\Requests;

use App\Modules\Auth\Application\DTOs\SocialLoginData;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function toData(SocialProvider $provider): SocialLoginData
    {
        return new SocialLoginData(
            provider: $provider,
            idToken: $this->validated('id_token'),
            name: $this->validated('name'),
            deviceName: $this->validated('device_name', 'api'),
        );
    }
}
