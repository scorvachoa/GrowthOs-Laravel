<?php

namespace App\Services;

use App\Models\ExtraTask;
use App\Models\User;
use App\Models\VideoTask;
use App\Enums\VideoTaskStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function stats(string $scope = 'week', ?User $authUser = null): array
    {
        $authUser ??= Auth::user();
        $orgId = $authUser?->activeOrganizationId();

        $today = Carbon::today();
        $labels = VideoTaskStatus::labels();

        return array_merge(
            $this->userStats($orgId, $today),
            $this->taskCounts(),
            $this->todayTasks($today, $labels),
            $this->periodStats($scope, $today),
            [
                'recent_activity' => $this->recentActivity(),
                'status_labels' => $labels,
                'period_label' => $this->periodLabel($scope),
            ]
        );
    }

    private function userStats(?int $orgId, Carbon $today): array
    {
        $base = User::where('organization_id', $orgId);

        return [
            'total_users' => $base->count(),
            'new_users' => (clone $base)
                ->where('created_at', '>=', $today)
                ->where('created_at', '<', $today->copy()->addDay())
                ->count(),
            'recent_users' => (clone $base)
                ->with('roles')
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'Super Admin'))
                ->latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'created_at']),
        ];
    }

    private function taskCounts(): array
    {
        $statusCounts = VideoTask::query()
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $inProgressStatuses = ['pending', 'script_ready', 'editing', 'review'];

        return [
            'total_video_tasks' => VideoTask::query()->count(),
            'total_extra_tasks' => ExtraTask::query()->count(),
            'status_counts' => $statusCounts,
            'in_progress' => $statusCounts->only($inProgressStatuses)->sum(),
            'completed' => $statusCounts->get('published', 0),
            'overdue' => VideoTask::query()
                ->whereNotIn('status', ['published', 'cancelled'])
                ->where('task_date', '<', Carbon::today())
                ->count(),
        ];
    }

    private function todayTasks(Carbon $today, array $labels): array
    {
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

        return [
            'today_tasks' => $todayTasks,
            'today_extra' => $todayExtra,
            'today_tasks_count' => $todayTasks->count(),
            'today_extra_count' => $todayExtra->count(),
        ];
    }

    private function periodStats(string $scope, Carbon $today): array
    {
        [$start, $end] = match ($scope) {
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            default => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfWeek(Carbon::SUNDAY)],
        };

        $periodTasks = VideoTask::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end->copy()->addDay())
            ->get();

        $periodTotal = $periodTasks->count();
        $periodCompleted = $periodTasks->where('status', 'published')->count();

        $publishedYesterday = VideoTask::query()
            ->where('task_date', '>=', $today->copy()->subDay())
            ->where('task_date', '<', $today)
            ->where('status', 'published')
            ->count();

        return [
            'period_completion' => $periodTotal > 0 ? round(($periodCompleted / $periodTotal) * 100) : 0,
            'published_yesterday' => $publishedYesterday,
        ];
    }

    private function recentActivity(): Collection
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'causer' => $a->causer ? ['name' => $a->causer->name] : null,
                'created_at' => $a->created_at?->diffForHumans(),
            ]);
    }

    private function periodLabel(string $scope): string
    {
        return match ($scope) {
            'year' => 'Anual',
            'month' => 'Mensual',
            default => 'Semanal',
        };
    }
}
