<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\WorkBlocks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = AppSetting::query()->first();

        return Inertia::render('Settings/Index', [
            'settings' => $settings ? [
                'use_blocks' => $settings->use_blocks,
                'block_hours' => $settings->block_hours,
                'show_youtube_chart' => $settings->show_youtube_chart,
            ] : [
                'use_blocks' => true,
                'block_hours' => 2,
                'show_youtube_chart' => true,
            ],
            'preview_blocks' => WorkBlocks::generate($settings?->block_hours ?? 2),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'use_blocks' => ['boolean'],
            'block_hours' => ['required', 'integer', 'in:1,2,3,4'],
            'show_youtube_chart' => ['boolean'],
        ]);

        $settings = AppSetting::query()->first();
        if (!$settings) {
            $settings = AppSetting::query()->create($validated);
        } else {
            $settings->update($validated);
        }

        return redirect()->back()->with('warning', 'Configuracion actualizada');
    }
}
