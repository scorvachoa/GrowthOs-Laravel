<?php

namespace Database\Factories;

use App\Models\ExtraTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtraTask>
 */
class ExtraTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'task_date' => fake()->dateTimeThisMonth(),
            'time_range' => '09:00-11:00',
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => 'pendiente',
        ];
    }
}
