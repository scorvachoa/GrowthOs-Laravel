<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'color' => '#'.fake()->hexColor(),
            'organization_id' => Organization::factory(),
        ];
    }
}
