<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Support\WorkBlocks;

class UserSettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $settings = $user->merged_settings;
        $workBlocks = WorkBlocks::generate($settings['block_hours']);

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'workBlocks' => $workBlocks,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'use_blocks' => 'sometimes|boolean',
            'block_hours' => 'sometimes|integer|min:1|max:4',
            'show_youtube_chart' => 'sometimes|boolean',
            'default_work_start' => 'sometimes|string|date_format:H:i',
            'default_work_end' => 'sometimes|string|date_format:H:i',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'integer|min:0|max:6',
            'max_tasks_per_block' => 'sometimes|integer|min:0|max:10',
            'default_report_scope' => 'sometimes|string|in:daily,weekly,monthly',
            'dashboard_default_view' => 'sometimes|string|in:week,month,year',
            'youtube_max_recent_videos' => 'sometimes|integer|min:1|max:50',
        ]);

        $settings = array_merge($user->merged_settings, $validated);
        $user->settings = $settings;
        $user->save();

        $workBlocks = WorkBlocks::generate($settings['block_hours']);

        session()->flash('success', 'Configuracion actualizada');

        return Inertia::render('Settings/Index', [
            'settings' => $user->merged_settings,
            'workBlocks' => $workBlocks,
        ]);
    }
}
