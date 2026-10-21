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
            'name' => 'channel_'.fake()->unique()->numberBetween(1, 1000000),
            'color' => '#'.fake()->hexColor(),
            'locales' => ['es'],
            'organization_id' => Organization::factory(),
        ];
    }
}
