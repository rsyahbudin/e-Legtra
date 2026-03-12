<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        $name = fake()->name();

        return [
            'USER_FULLNAME' => $name,
            'USER_EMAIL' => fake()->unique()->safeEmail(),
            'USER_ID' => fake()->unique()->numerify('##########'),
            'USER_PASSWORD' => static::$password ??= Hash::make('password'),
            'DIV_ID' => null,
            'DEPT_ID' => null,
            'USER_ROLE_ID' => \App\Models\Role::factory(),
        ];
    }

    /**
     * Indicate that the user should not have two-factor authentication enabled.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'USER_EMAIL_VERIFIED_DT' => null,
        ]);
    }
}
