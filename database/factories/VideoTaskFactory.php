<?php

namespace Database\Factories;

use App\Enums\VideoTaskStatus;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoTask>
 */
class VideoTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'channel_id' => Channel::factory(),
            'task_date' => fake()->dateTimeThisMonth(),
            'time_range' => '09:00-11:00',
            'title' => fake()->sentence(3),
            'script' => fake()->paragraph(),
            'copy' => fake()->paragraph(),
            'status' => VideoTaskStatus::Pending->value,
            'is_pending' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'is_pending' => true,
            'task_date' => null,
            'time_range' => null,
        ]);
    }
}
