<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'matric_number' => fake()->unique()->numerify('20######'),
            'telegram_chat_id' => fake()->optional()->numerify('##########'),
            'role' => fake()->randomElement(['Admin', 'Security']),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }
}
