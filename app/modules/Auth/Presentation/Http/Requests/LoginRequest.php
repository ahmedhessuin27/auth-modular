<?php

namespace App\Modules\Auth\Presentation\Http\Requests;

use App\Modules\Auth\Application\DTOs\LoginData;
use App\Modules\Auth\Presentation\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use NormalizesEmail;

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
