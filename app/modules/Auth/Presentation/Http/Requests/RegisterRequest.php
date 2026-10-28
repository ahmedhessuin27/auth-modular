<?php

namespace App\Modules\Users\Presentation\Http\Requests;

use App\Models\User;
use App\Modules\Users\Application\DTOs\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
            deviceName: $this->validated('device_name', 'api'),
        );
    }
}
