<?php

namespace Database\Factories;

use App\Models\SecurityGuard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityGuard>
 */
class SecurityGuardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_number' => '+3934'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
