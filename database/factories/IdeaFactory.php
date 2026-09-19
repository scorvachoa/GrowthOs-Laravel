<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Idea;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
class IdeaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel_id' => Channel::factory(),
            'content' => fake()->sentence(),
            'is_used' => false,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'is_used' => true,
        ]);
    }
}
