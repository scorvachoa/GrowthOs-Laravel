<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacation>
 */
class VacationFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeThisYear();
        $end = (clone $start)->modify('+14 days');

        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'type' => 'parcial',
            'status' => 'pendiente',
            'days_used' => 15,
            'year' => (int) $start->format('Y'),
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
