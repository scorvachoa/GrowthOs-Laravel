<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\TimeOff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'date' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'type' => 'personal',
            'reason' => fake()->sentence(),
            'status' => 'pendiente',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'aprobado',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
