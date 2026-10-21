<?php

namespace App\Services;

use App\Enums\VideoTaskStatus;
use App\Models\ExtraTask;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VideoTask;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    private bool $isManager;

    public function stats(string $scope = 'week', ?User $authUser = null): array
    {
        $authUser ??= Auth::user();
        $orgId = $authUser?->activeOrganizationId();
        $this->isManager = $authUser->hasRole(['Super Admin', 'Admin']);

        $today = Carbon::today();
        $labels = VideoTaskStatus::labels();

        return array_merge(
            $this->userStats($orgId, $today),
            $this->taskCounts($authUser),
            $this->todayTasks($today, $authUser),
            $this->periodStats($scope, $today, $authUser),
            [
                'recent_activity' => $this->recentActivity($orgId),
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

    private function taskCounts(User $authUser): array
    {
        $videoQuery = VideoTask::query()->visibleTo()->where('is_pending', false);
        $extraQuery = ExtraTask::query()->visibleTo();

        $statusCounts = (clone $videoQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $inProgressStatuses = ['pending', 'script_ready', 'editing', 'review'];

        return [
            'total_video_tasks' => (clone $videoQuery)->count(),
            'total_extra_tasks' => (clone $extraQuery)->count(),
            'status_counts' => $statusCounts,
            'in_progress' => $statusCounts->only($inProgressStatuses)->sum(),
            'completed' => $statusCounts->get('published', 0),
            'overdue' => (clone $videoQuery)->overdue()->count(),
        ];
    }

    private function todayTasks(Carbon $today, User $authUser): array
    {
        $todayTasks = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->where('task_date', '>=', $today)
            ->where('task_date', '<', $today->copy()->addDay())
            ->orderBy('time_range')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'time_range' => $t->time_range,
                'status' => $t->status,
                'status_label' => VideoTaskStatus::labelFor($t->status),
                'is_session' => false,
            ]);

        $orgId = $authUser->activeOrganizationId();
        $todaySessions = WorkSession::query()
            ->with('videoTask')
            ->whereHas('videoTask', fn ($q) => $q->visibleTo())
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
            ->visibleTo()
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

    private function periodStats(string $scope, Carbon $today, User $authUser): array
    {
        [$start, $end] = match ($scope) {
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            default => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfWeek(Carbon::SUNDAY)],
        };

        // Agregado en SQL: antes se hidrataban todas las tareas del periodo
        // sólo para contar total y publicadas.
        $agg = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end->copy()->addDay())
            ->selectRaw("count(*) as period_total, sum(case when status = 'published' then 1 else 0 end) as period_completed")
            ->toBase()
            ->first();

        $periodTotal = (int) ($agg->period_total ?? 0);
        $periodCompleted = (int) ($agg->period_completed ?? 0);

        $publishedYesterday = VideoTask::query()
            ->visibleTo()
            ->where('task_date', '>=', $today->copy()->subDay())
            ->where('task_date', '<', $today)
            ->where('status', 'published')
            ->count();

        return [
            'period_completion' => $periodTotal > 0 ? round(($periodCompleted / $periodTotal) * 100) : 0,
            'published_yesterday' => $publishedYesterday,
        ];
    }

    private function recentActivity(?int $orgId): Collection
    {
        $orgUserIds = User::where('organization_id', $orgId)->pluck('id');

        return Activity::query()
            ->with('causer')
            ->whereIn('causer_id', $orgUserIds)
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

    public function weeklySummaryContext(?User $authUser = null): array
    {
        $authUser ??= Auth::user();
        $stats = $this->stats('week', $authUser);
        $today = Carbon::today();
        $start = $today->copy()->startOfWeek(Carbon::MONDAY);
        $end = $today->copy()->endOfWeek(Carbon::SUNDAY);

        // Totales y por_estado son all-time: se agregan en SQL (con el estado
        // de la última sesión como subquery correlacionada) en vez de
        // hidratar toda la historia de la organización.
        $agg = $this->allTimeTaskAggregates($today);

        // La semana sí se carga a nivel de fila (es la que se muestra) pero
        // ya filtrada por fecha en SQL.
        $weekTasks = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end->copy()->addDay())
            ->with('channel:id,name')
            ->withCount([
                'sessions as sesiones_total',
                'sessions as sesiones_completadas' => fn ($q) => $q->withoutGlobalScope('organization')->where('status', 'completed'),
            ])
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get(['id', 'title', 'status', 'task_date', 'time_range', 'channel_id']);

        $latestSessionStatus = WorkSession::query()
            ->withoutGlobalScope('organization')
            ->whereIn('video_task_id', $weekTasks->pluck('id'))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['video_task_id', 'status'])
            ->unique('video_task_id')
            ->mapWithKeys(fn ($session) => [$session->video_task_id => $session->status]);

        foreach ($weekTasks as $task) {
            $task->latest_session_status = $latestSessionStatus->get($task->id);
        }

        $stateOrder = [
            VideoTaskStatus::labelFor('published') => (int) $agg->cnt_published,
            VideoTaskStatus::labelFor('cancelled') => (int) $agg->cnt_cancelled,
            'Completado' => (int) $agg->cnt_session_completed,
            VideoTaskStatus::labelFor('pending') => (int) $agg->cnt_pending,
            VideoTaskStatus::labelFor('script_ready') => (int) $agg->cnt_script_ready,
            VideoTaskStatus::labelFor('editing') => (int) $agg->cnt_editing,
            VideoTaskStatus::labelFor('review') => (int) $agg->cnt_review,
            VideoTaskStatus::labelFor('scheduled') => (int) $agg->cnt_scheduled,
        ];
        $stateCounts = array_filter($stateOrder);

        $isDone = fn ($t) => in_array($this->videoState($t), ['published', 'session_completed'], true);
        $isCancelled = fn ($t) => $this->videoState($t) === 'cancelled';
        $isOverdue = fn ($t) => ! $isDone($t) && ! $isCancelled($t) && $t->task_date->lt($today);

        $weekTotal = $weekTasks->count();
        $weekPublished = $weekTasks->where('status', 'published')->count();
        $weekSessionCompleted = $weekTasks->filter(fn ($t) => $this->videoState($t) === 'session_completed')->count();
        $weekCompleted = $weekPublished + $weekSessionCompleted;
        $weekCancelled = $weekTasks->filter($isCancelled)->count();

        return [
            'periodo' => $start->format('d/m').' - '.$end->format('d/m/Y'),
            'totales' => [
                'tareas_video' => (int) $agg->tareas_video,
                'tareas_extra' => $stats['total_extra_tasks'],
                'en_progreso' => (int) $agg->cnt_en_progreso,
                'completadas' => (int) $agg->cnt_published + (int) $agg->cnt_session_completed,
                'vencidas' => (int) $agg->cnt_vencidas,
            ],
            'totales_semana' => [
                'tareas' => $weekTotal,
                'publicadas' => $weekPublished,
                'completadas_por_sesion' => $weekSessionCompleted,
                'completadas_total' => $weekCompleted,
                'en_progreso' => $weekTotal - $weekCompleted - $weekCancelled,
                'canceladas' => $weekCancelled,
                'vencidas' => $weekTasks->filter($isOverdue)->count(),
            ],
            'por_estado' => $stateCounts,
            'cumplimiento_semana_pct' => $weekTotal > 0 ? (int) round(($weekCompleted / $weekTotal) * 100) : 0,
            'publicadas_ayer' => $stats['published_yesterday'],
            'ausencias_hoy' => $stats['today_absences'],
            'aprobaciones_pendientes' => $stats['pending_approvals']['total'] ?? 0,
            'tareas_de_la_semana' => $weekTasks->map(function ($t) {
                $estado = $this->videoStateLabel($t);
                $estadoTarea = VideoTaskStatus::labelFor($t->status);

                $row = [
                    'fecha' => $t->task_date->format('d/m'),
                    'titulo' => $t->title,
                    'estado' => $estado,
                    'canal' => $t->channel?->name,
                ];

                if ($estado !== $estadoTarea) {
                    $row['estado_tarea'] = $estadoTarea;
                }
                if ((int) $t->sesiones_total > 0) {
                    $row['sesiones'] = $t->sesiones_completadas.'/'.$t->sesiones_total.' completadas';
                }

                return $row;
            })->all(),
        ];
    }

    /**
     * Conteos all-time de video_tasks (session-aware) calculados en una sola
     * consulta: total, publicadas, completadas por sesión, por estado y
     * vencidas. latest_status replica la semántica de videoState() con la
     * última sesión de cada tarea.
     */
    private function allTimeTaskAggregates(Carbon $today): object
    {
        $base = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->selectRaw(
                'video_tasks.status as status, video_tasks.task_date as task_date, '.
                'coalesce((select ws.status from work_sessions ws '.
                'where ws.video_task_id = video_tasks.id '.
                "order by ws.date desc, ws.id desc limit 1), '') as latest_status"
            );

        return DB::query()
            ->fromSub($base, 'tasks')
            ->selectRaw("
                count(*) as tareas_video,
                sum(case when status = 'published' then 1 else 0 end) as cnt_published,
                sum(case when status = 'cancelled' then 1 else 0 end) as cnt_cancelled,
                sum(case when status not in ('published','cancelled') and latest_status = 'completed' then 1 else 0 end) as cnt_session_completed,
                sum(case when status in ('pending','script_ready','editing','review') and latest_status <> 'completed' then 1 else 0 end) as cnt_en_progreso,
                sum(case when status = 'pending' and latest_status <> 'completed' then 1 else 0 end) as cnt_pending,
                sum(case when status = 'script_ready' and latest_status <> 'completed' then 1 else 0 end) as cnt_script_ready,
                sum(case when status = 'editing' and latest_status <> 'completed' then 1 else 0 end) as cnt_editing,
                sum(case when status = 'review' and latest_status <> 'completed' then 1 else 0 end) as cnt_review,
                sum(case when status = 'scheduled' and latest_status <> 'completed' then 1 else 0 end) as cnt_scheduled,
                sum(case when status not in ('published','cancelled') and latest_status <> 'completed' and task_date < ? then 1 else 0 end) as cnt_vencidas
            ", [$today->toDateString()])
            ->first();
    }

    private function videoState(VideoTask $task): string
    {
        if ($task->status === 'published') {
            return 'published';
        }

        if ($task->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->latestSessionStatus($task) === 'completed') {
            return 'session_completed';
        }

        return (string) $task->status;
    }

    private function latestSessionStatus(VideoTask $task): ?string
    {
        if (array_key_exists('latest_session_status', $task->getAttributes())) {
            return $task->latest_session_status;
        }

        return $task->sessions()
            ->reorder()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('status');
    }

    private function videoStateLabel(VideoTask $task): string
    {
        $state = $this->videoState($task);

        if ($state === 'session_completed') {
            return 'Completado';
        }

        return VideoTaskStatus::labelFor($state);
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
