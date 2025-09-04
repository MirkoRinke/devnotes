<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory {
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'name' => fake()->unique()->name(),
            'display_name' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'avatar_items' => [
                'duck' => null,
                'head_accessory' => null,
                'eye_accessory' => null,
                'ear_accessory' => null,
                'neck_accessory' => null,
                'chest_accessory' => null,
                'background' => null,
            ],
            'account_purpose' => 'regular',
            // 'remember_token' => Str::random(10),
            'moderation_info' => [],
            'privacy_policy_accepted_at' => now(),
            'terms_of_service_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
