<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Support\WorkBlocks;
use App\Services\BackupService;

class UserSettingsController extends Controller
{
    public function index(BackupService $backupService)
    {
        $user = Auth::user();
        $settings = $user->merged_settings;
        $workBlocks = WorkBlocks::fromSettings($settings);

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'workBlocks' => $workBlocks,
            'backup_schedule' => $user->hasRole('Super Admin') ? $backupService->getSchedule() : null,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $settingsPermissions = ['configure work hours', 'configure youtube', 'configure dashboard', 'configure backup'];
        if (!$user->hasAnyPermission($settingsPermissions)) abort(403);

        $validated = $request->validate([
            'use_blocks' => 'sometimes|boolean',
            'block_hours' => 'sometimes|integer|min:1|max:4',
            'show_youtube_chart' => 'sometimes|boolean',
            'default_work_start' => 'sometimes|string|date_format:H:i',
            'default_work_end' => 'sometimes|string|date_format:H:i',
            'lunch_start' => 'sometimes|string|date_format:H:i',
            'lunch_end' => 'sometimes|string|date_format:H:i',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'integer|min:0|max:6',
            'max_tasks_per_block' => 'sometimes|integer|min:0|max:10',
            'default_report_scope' => 'sometimes|string|in:mensual,semanal,dia,anual',
            'dashboard_default_view' => 'sometimes|string|in:week,month,year',
            'youtube_max_recent_videos' => 'sometimes|integer|min:1|max:50',
            'languages' => 'sometimes|array',
            'languages.*' => 'string|size:2',
        ]);

        $user->settings = array_merge($user->settings ?? [], $validated);
        $user->save();

        return redirect()->route('settings.index')->with('success', 'Configuracion actualizada');
    }

    public function updateBackupSchedule(Request $request, BackupService $backupService)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('configure backup')) abort(403);

        $validated = $request->validate([
            'time' => 'required|string|date_format:H:i',
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $backupService->setSchedule($validated['time'], $validated['day']);

        return redirect()->route('settings.index')->with('success', 'Horario de backup actualizado');
    }
}
