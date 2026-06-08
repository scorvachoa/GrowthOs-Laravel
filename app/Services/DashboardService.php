<?php

namespace App\Services;

use App\Models\ExtraTask;
use App\Models\User;
use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function stats(string $scope = 'week', ?User $authUser = null): array
    {
        $authUser ??= Auth::user();
        $orgId = $authUser?->activeOrganizationId();

        $userBase = fn () => User::where('organization_id', $orgId);
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $totalVideoTasks = VideoTask::query()->count();
        $totalExtraTasks = ExtraTask::query()->count();

        $statusCounts = VideoTask::query()
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = VideoTaskStatuses::labels();
        $inProgressStatuses = ['pending', 'script_ready', 'editing', 'review'];
        $inProgress = $statusCounts->only($inProgressStatuses)->sum();
        $completed = $statusCounts->get('published', 0);

        $overdue = VideoTask::query()
            ->whereNotIn('status', ['published', 'cancelled'])
            ->where('task_date', '<', $today)
            ->count();

        $todayTasks = VideoTask::query()
            ->where('task_date', '>=', $today)
            ->where('task_date', '<', $today->copy()->addDay())
            ->orderBy('time_range')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'time_range' => $t->time_range,
                'status' => $t->status,
                'status_label' => $labels[$t->status] ?? $t->status,
            ]);

        $todayExtra = ExtraTask::query()
            ->where('task_date', '>=', $today)
            ->where('task_date', '<', $today->copy()->addDay())
            ->orderBy('time_range')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'time_range' => $t->time_range,
                'status' => $t->status,
                'location' => $t->location,
            ]);

        $periodTasks = match ($scope) {
            'year' => VideoTask::query()
                ->where('task_date', '>=', $yearStart)
                ->where('task_date', '<', $yearEnd->copy()->addDay())
                ->get(),
            'month' => VideoTask::query()
                ->where('task_date', '>=', $monthStart)
                ->where('task_date', '<', $monthEnd->copy()->addDay())
                ->get(),
            default => VideoTask::query()
                ->where('task_date', '>=', $weekStart)
                ->where('task_date', '<', $weekEnd->copy()->addDay())
                ->get(),
        };

        $periodTotal = $periodTasks->count();
        $periodCompleted = $periodTasks->where('status', 'published')->count();
        $periodCompletion = $periodTotal > 0 ? round(($periodCompleted / $periodTotal) * 100) : 0;

        $publishedYesterday = VideoTask::query()
            ->where('task_date', '>=', $today->copy()->subDay())
            ->where('task_date', '<', $today)
            ->where('status', 'published')
            ->count();

        return [
            'total_users' => $userBase()->count(),
            'new_users' => $userBase()
                ->where('created_at', '>=', $today)
                ->where('created_at', '<', $today->copy()->addDay())
                ->count(),
            'recent_users' => $userBase()
                ->with('roles')
                ->latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'created_at']),
            'recent_activity' => Activity::query()
                ->with('causer')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'causer' => $a->causer ? ['name' => $a->causer->name] : null,
                    'created_at' => $a->created_at?->diffForHumans(),
                ]),

            'total_video_tasks' => $totalVideoTasks,
            'total_extra_tasks' => $totalExtraTasks,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'overdue' => $overdue,
            'today_tasks' => $todayTasks,
            'today_extra' => $todayExtra,
            'today_tasks_count' => $todayTasks->count(),
            'today_extra_count' => $todayExtra->count(),
            'period_completion' => $periodCompletion,
            'period_label' => match ($scope) {
                'year' => 'Anual',
                'month' => 'Mensual',
                default => 'Semanal',
            },
            'published_yesterday' => $publishedYesterday,
            'status_labels' => $labels,
            'status_counts' => $statusCounts,
        ];
    }
}
