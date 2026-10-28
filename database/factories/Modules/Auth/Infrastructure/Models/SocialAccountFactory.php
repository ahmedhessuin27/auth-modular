<?php

namespace Database\Factories\Modules\Auth\Infrastructure\Models;

use App\Models\User;
use App\Modules\Auth\Domain\Enums\SocialProvider;
use App\Modules\Auth\Infrastructure\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Laravel only infers the model for factories of App\Models classes.
     *
     * @var class-string<SocialAccount>
     */
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(SocialProvider::cases()),
            'provider_user_id' => (string) fake()->unique()->randomNumber(9, true),
        ];
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => SocialProvider::Google,
        ]);
    }
}
