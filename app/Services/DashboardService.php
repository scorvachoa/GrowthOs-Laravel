<?php

namespace App\Services;

use App\Models\ExtraTask;
use App\Models\User;
use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function stats(): array
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $totalVideoTasks = VideoTask::query()->count();
        $totalExtraTasks = ExtraTask::query()->count();

        $inProgress = VideoTask::query()
            ->whereIn('status', ['pending', 'script_ready', 'editing', 'review'])
            ->count();

        $completed = VideoTask::query()
            ->where('status', 'published')
            ->count();

        $overdue = VideoTask::query()
            ->whereNotIn('status', ['published', 'cancelled'])
            ->where('task_date', '<', $today)
            ->count();

        $todayTasks = VideoTask::query()
            ->whereDate('task_date', $today)
            ->orderBy('time_range')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'time_range' => $t->time_range,
                'status' => $t->status,
                'status_label' => VideoTaskStatuses::labels()[$t->status] ?? $t->status,
            ]);

        $todayExtra = ExtraTask::query()
            ->whereDate('task_date', $today)
            ->orderBy('time_range')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'time_range' => $t->time_range,
                'status' => $t->status,
                'location' => $t->location,
            ]);

        $weekTasks = VideoTask::query()
            ->where('task_date', '>=', $weekStart)
            ->where('task_date', '<=', $weekEnd)
            ->get();

        $weekTotal = $weekTasks->count();
        $weekCompleted = $weekTasks->where('status', 'published')->count();
        $weeklyCompletion = $weekTotal > 0 ? round(($weekCompleted / $weekTotal) * 100) : 0;

        $publishedYesterday = VideoTask::query()
            ->whereDate('task_date', $today->copy()->subDay())
            ->where('status', 'published')
            ->count();

        $labels = VideoTaskStatuses::labels();

        return [
            'total_users' => User::count(),
            'new_users' => User::query()->whereDate('created_at', $today)->count(),
            'recent_users' => User::query()->latest()->take(5)->get(['id', 'name', 'email', 'created_at']),
            'recent_activity' => Activity::query()->latest()->take(10)->get(),

            'total_video_tasks' => $totalVideoTasks,
            'total_extra_tasks' => $totalExtraTasks,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'overdue' => $overdue,
            'today_tasks' => $todayTasks,
            'today_extra' => $todayExtra,
            'today_tasks_count' => $todayTasks->count(),
            'today_extra_count' => $todayExtra->count(),
            'weekly_completion' => $weeklyCompletion,
            'published_yesterday' => $publishedYesterday,
            'status_labels' => $labels,
        ];
    }
}
