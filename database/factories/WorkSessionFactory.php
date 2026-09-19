<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\VideoTask;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSession>
 */
class WorkSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'video_task_id' => VideoTask::factory(),
            'organization_id' => Organization::factory(),
            'date' => fake()->dateTimeThisMonth(),
            'time_range' => '09:00-11:00',
            'status' => 'pending',
        ];
    }
}
