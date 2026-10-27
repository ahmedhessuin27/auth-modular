<?php

namespace App\Modules\Users\Presentation\Http\Requests;

use App\Modules\Users\Application\DTOs\LoginData;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function toData(): LoginData
    {
        return new LoginData(
            email: $this->validated('email'),
            password: $this->validated('password'),
            deviceName: $this->validated('device_name', 'api'),
        );
    }
}
