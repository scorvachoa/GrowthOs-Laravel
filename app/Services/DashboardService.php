<?php

namespace App\Services;

use App\Models\ExtraTask;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VideoTask;
use App\Models\WorkSession;
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
            $this->todayTasks($today, $labels, $orgId),
            $this->periodStats($scope, $today),
            [
                'recent_activity' => $this->recentActivity(),
                'status_labels' => $labels,
                'period_label' => $this->periodLabel($scope),
                'today_absences' => $this->todayAbsences($orgId, $today),
                'pending_approvals' => $this->pendingApprovals($orgId),
                'can_approve_absences' => $authUser->hasRole('Super Admin') || $authUser->can('approve vacations') || $authUser->can('approve time off'),
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

    private function todayTasks(Carbon $today, array $labels, ?int $orgId = null): array
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
                'is_session' => false,
            ]);

        $todaySessions = WorkSession::query()
            ->with('videoTask')
            ->whereHas('videoTask', fn ($q) => $q->when($orgId, fn ($q) => $q->where('organization_id', $orgId)))
            ->where('date', '>=', $today)
            ->where('date', '<', $today->copy()->addDay())
            ->get()
            ->map(fn ($s) => [
                'id' => $s->video_task_id,
                'title' => $s->videoTask?->title ?? 'Sin título',
                'time_range' => $s->time_range,
                'status' => $s->status === 'completed' ? 'completed' : 'in_progress',
                'status_label' => $s->status === 'completed' ? 'Completado' : 'En progreso',
                'is_session' => true,
            ]);

        $todayTasks = $todayTasks->concat($todaySessions)->sortBy('time_range')->values();

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

    private function todayAbsences(?int $orgId, Carbon $today): array
    {
        $vacationUsers = User::where('organization_id', $orgId)
            ->whereHas('vacations', fn ($q) => $q
                ->where('status', 'aprobado')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
            )->pluck('name');

        $timeOffUsers = User::where('organization_id', $orgId)
            ->whereHas('timeOffs', fn ($q) => $q
                ->where('status', 'aprobado')
                ->where('date', '=', $today)
            )->pluck('name');

        return [
            'vacations' => $vacationUsers,
            'time_offs' => $timeOffUsers,
        ];
    }

    private function pendingApprovals(?int $orgId): array
    {
        $pendingVacations = Vacation::where('status', 'pendiente')
            ->whereHas('user', fn ($q) => $q->where('organization_id', $orgId))
            ->with('user')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'type' => 'vacation',
                'user_name' => $v->user?->name,
                'start_date' => $v->start_date->format('Y-m-d'),
                'end_date' => $v->end_date->format('Y-m-d'),
                'days_used' => $v->days_used,
                'reason' => $v->reason,
                'created_at' => $v->created_at->diffForHumans(),
            ]);

        $pendingTimeOffs = TimeOff::where('status', 'pendiente')
            ->whereHas('user', fn ($q) => $q->where('organization_id', $orgId))
            ->with('user')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => 'time_off',
                'user_name' => $t->user?->name,
                'date' => $t->date->format('Y-m-d'),
                'start_time' => $t->start_time,
                'end_time' => $t->end_time,
                'reason' => $t->reason,
                'created_at' => $t->created_at->diffForHumans(),
            ]);

        return [
            'vacations' => $pendingVacations,
            'time_offs' => $pendingTimeOffs,
            'total' => $pendingVacations->count() + $pendingTimeOffs->count(),
        ];
    }
}
