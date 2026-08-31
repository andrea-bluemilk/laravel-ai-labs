<?php

namespace Database\Factories;

use App\Enums\CheckinStatus;
use App\Models\Checkin;
use App\Models\SecurityGuard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'security_guard_id' => SecurityGuard::factory(),
            'status' => CheckinStatus::CALLED_PENDING,
            'called_at' => now(),
        ];
    }
}
