<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::query()->firstOrCreate([], [
            'use_blocks' => true,
            'block_hours' => 2,
            'show_youtube_chart' => true,
        ]);
    }
}
