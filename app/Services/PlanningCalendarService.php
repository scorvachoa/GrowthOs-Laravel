<?php

namespace App\Services;

use App\Models\DayObservation;
use App\Models\ExtraTask;
use App\Models\TimeOff;
use App\Models\Vacation;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Enums\VideoTaskStatus;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PlanningCalendarService
{
    public function __construct(
        private PeruHolidayService $holidays,
    ) {}

    public static function bustCache(?int $userId = null): void
    {
        Cache::increment('planning_bust_' . ($userId ?? Auth::id() ?? 'system'));
    }

    public function snapshot(int $year, int $month, ?Carbon $weekStart = null, ?array $workBlocks = null): array
    {
        $workBlocks ??= WorkBlocks::all();
        $weekStart = $weekStart ?? Carbon::today()->startOfWeek(Carbon::MONDAY);

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->addMonth();

        $monthTasks = $this->loadMonthTasks($monthStart, $monthEnd);
        $monthExtraTasks = $this->loadMonthExtraTasks($monthStart, $monthEnd);
        $observationsMap = $this->loadObservationsMap($monthStart, $monthEnd);
        $absencesMap = $this->loadAbsencesMap($monthStart, $monthEnd);

        [$tasksCount, $blocksMap, $tasksDetailMap] = $this->buildMonthMaps($monthTasks, $workBlocks);
        $hasExtraTasksMap = $this->buildExtraTasksMap($monthExtraTasks);

        [$weekBlockMap, $weekTasksDetailMap, $weekExtraTasksDetailMap] = $this->buildWeekMaps($weekStart, $workBlocks);

        return [
            'year' => $year,
            'month' => $month,
            'today' => Carbon::today()->format('Y-m-d'),
            'week_start' => $weekStart->format('Y-m-d'),
            'tasks_count' => $tasksCount,
            'blocks_map' => $blocksMap,
            'week_blocks_map' => $weekBlockMap,
            'tasks_detail_map' => $tasksDetailMap,
            'has_extra_tasks_map' => $hasExtraTasksMap,
            'week_tasks_detail_map' => $weekTasksDetailMap,
            'week_extra_tasks_detail_map' => $weekExtraTasksDetailMap,
            'observations_map' => $observationsMap,
            'absences_map' => $absencesMap,
            'holidays_map' => $this->holidays->forYear($year),
            'work_blocks' => $workBlocks,
            'statuses' => VideoTaskStatus::options(),
        ];
    }

    private function loadMonthTasks(Carbon $start, Carbon $end): Collection
    {
        $tasks = VideoTask::query()
            ->with('channel', 'sessions')
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $map = [];

        foreach ($tasks as $task) {
            $primaryKey = $task->task_date->format('Y-m-d');
            $map[$primaryKey][] = $task;

            foreach ($task->sessions as $session) {
                $sessionKey = $session->date->format('Y-m-d');
                if ($sessionKey === $primaryKey) continue;
                if ($sessionKey < $start->format('Y-m-d') || $sessionKey >= $end->format('Y-m-d')) continue;
                $map[$sessionKey][] = (object)[
                    'id' => $task->id,
                    'task_date' => $session->date,
                    'time_range' => $session->time_range,
                    'title' => $task->title,
                    'status' => $session->status,
                    'channel' => $task->channel,
                    'sessions' => collect(),
                    'is_session' => true,
                ];
            }
        }

        return collect($map)->map(fn ($day) => collect($day));
    }

    private function loadMonthExtraTasks(Carbon $start, Carbon $end): Collection
    {
        return ExtraTask::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->get()
            ->groupBy(fn (ExtraTask $task) => $task->task_date->format('Y-m-d'));
    }

    private function loadAbsencesMap(Carbon $start, Carbon $end): array
    {
        $orgUsers = \App\Models\User::where('organization_id', Auth::user()->activeOrganizationId())
            ->pluck('id');

        $vacations = Vacation::query()
            ->whereIn('user_id', $orgUsers)
            ->where('status', 'aprobado')
            ->where('start_date', '<', $end)
            ->where('end_date', '>=', $start)
            ->get(['user_id', 'start_date', 'end_date', 'type']);

        $timeOffs = TimeOff::query()
            ->whereIn('user_id', $orgUsers)
            ->where('status', 'aprobado')
            ->where('date', '>=', $start)
            ->where('date', '<', $end)
            ->get(['user_id', 'date', 'type']);

        $map = [];

        foreach ($vacations as $v) {
            $s = $v->start_date instanceof Carbon ? $v->start_date : Carbon::parse($v->start_date);
            $e = $v->end_date instanceof Carbon ? $v->end_date : Carbon::parse($v->end_date);
            $cursor = $s->copy()->max($start);
            $rangeEnd = $e->copy()->min($end->copy()->subDay());
            while ($cursor <= $rangeEnd) {
                $key = $cursor->format('Y-m-d');
                $map[$key][] = [
                    'type' => 'vacation',
                    'label' => 'Vacaciones',
                ];
                $cursor->addDay();
            }
        }

        foreach ($timeOffs as $t) {
            $key = $t->date instanceof Carbon ? $t->date->format('Y-m-d') : Carbon::parse($t->date)->format('Y-m-d');
            $map[$key][] = [
                'type' => 'time_off',
                'label' => 'Permiso: ' . match ($t->type) {
                    'medico' => 'Medico',
                    'personal' => 'Personal',
                    'tramite' => 'Tramite',
                    default => 'Otro',
                },
            ];
        }

        return $map;
    }

    private function loadObservationsMap(Carbon $start, Carbon $end): array
    {
        return DayObservation::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->where('organization_id', Auth::user()->activeOrganizationId())
            ->get()
            ->keyBy(fn (DayObservation $obs) => $obs->task_date->format('Y-m-d'))
            ->map(fn (DayObservation $obs) => true)
            ->all();
    }

    private function buildMonthMaps(Collection $monthTasks, array $workBlocks): array
    {
        $tasksCount = [];
        $blocksMap = [];
        $tasksDetailMap = [];

        foreach ($monthTasks as $dayKey => $dayTasks) {
            $tasksCount[$dayKey] = $dayTasks->count();
            $blocksMap[$dayKey] = $this->buildBlockCounts($dayTasks, $workBlocks);
            $tasksDetailMap[$dayKey] = $dayTasks
                ->map(fn ($task) => $this->serializeSummary($task))
                ->values()
                ->all();
        }

        return [$tasksCount, $blocksMap, $tasksDetailMap];
    }

    private function buildExtraTasksMap(Collection $extraTasks): array
    {
        $map = [];
        foreach ($extraTasks as $dayKey => $tasks) {
            $map[$dayKey] = $tasks->count();
        }
        return $map;
    }

    private function buildWeekMaps(Carbon $weekStart, array $workBlocks): array
    {
        $weekEnd = $weekStart->copy()->addDays(7);

        $weekTasks = VideoTask::query()
            ->with('channel', 'sessions')
            ->where('task_date', '>=', $weekStart)
            ->where('task_date', '<', $weekEnd)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $weekExtraTasks = ExtraTask::query()
            ->where('task_date', '>=', $weekStart)
            ->where('task_date', '<', $weekEnd)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $weekBlockMap = [];
        $weekTasksDetailMap = [];
        $weekExtraTasksDetailMap = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $iso = $weekStart->copy()->addDays($offset)->format('Y-m-d');
            $weekBlockMap[$iso] = WorkBlocks::emptyCounts($workBlocks);
            $weekTasksDetailMap[$iso] = [];
            $weekExtraTasksDetailMap[$iso] = [];
        }

        foreach ($weekTasks as $task) {
            $primaryKey = $task->task_date->format('Y-m-d');

            if (isset($weekBlockMap[$primaryKey])) {
                if (isset($weekBlockMap[$primaryKey][$task->time_range])) {
                    $weekBlockMap[$primaryKey][$task->time_range]++;
                }
                $weekTasksDetailMap[$primaryKey][] = $this->serializeSummary($task);
            }

            foreach ($task->sessions as $session) {
                $sessionKey = $session->date->format('Y-m-d');
                if ($sessionKey === $primaryKey) continue;
                if (!isset($weekBlockMap[$sessionKey])) continue;

                if ($session->time_range && isset($weekBlockMap[$sessionKey][$session->time_range])) {
                    $weekBlockMap[$sessionKey][$session->time_range]++;
                }
                $weekTasksDetailMap[$sessionKey][] = [
                    'id' => $task->id,
                    'session_id' => $session->id,
                    'time_range' => $session->time_range,
                    'title' => $task->title,
                    'status' => $session->status,
                    'is_session' => true,
                    'channel' => $task->channel
                        ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                        : null,
                ];
            }
        }

        foreach ($weekExtraTasks as $task) {
            $iso = $task->task_date->format('Y-m-d');
            if (isset($weekExtraTasksDetailMap[$iso])) {
                $weekExtraTasksDetailMap[$iso][] = [
                    'id' => 'e' . $task->id,
                    'task_date' => $task->task_date->format('Y-m-d'),
                    'time_range' => $task->time_range,
                    'title' => $task->title,
                    'status' => $task->status,
                    'location' => $task->location,
                    'is_extra' => true,
                ];
            }
        }

        return [$weekBlockMap, $weekTasksDetailMap, $weekExtraTasksDetailMap];
    }

    public function tasksForDate(string $date): array
    {
        $tasks = VideoTask::query()
            ->with('channel')
            ->where('task_date', '>=', $date)
            ->where('task_date', '<', Carbon::parse($date)->addDay())
            ->orderBy('time_range')
            ->get()
            ->map(fn (VideoTask $task) => $this->serializeDetail($task))
            ->values()
            ->all();

        $sessionEntries = WorkSession::where('date', $date)
            ->with('videoTask.channel')
            ->get()
            ->filter(fn ($s) => $s->videoTask->task_date->format('Y-m-d') !== $date)
            ->map(fn ($s) => $this->serializeDetailFromSession($s))
            ->values()
            ->all();

        return array_merge($tasks, $sessionEntries);
    }

    private function serializeDetailFromSession(WorkSession $session): array
    {
        $task = $session->videoTask;
        return [
            'id' => $task->id,
            'session_id' => $session->id,
            'task_date' => $session->date->format('Y-m-d'),
            'time_range' => $session->time_range,
            'title' => $task->title,
            'script' => $task->script,
            'copy' => $task->copy,
            'key_phrases' => $task->key_phrases,
            'youtube_url' => $task->youtube_url,
            'status' => $session->status,
            'is_session' => true,
            'channel' => $task->channel
                ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
        ];
    }

    private function buildBlockCounts(Collection $dayTasks, array $workBlocks): array
    {
        $counts = WorkBlocks::emptyCounts($workBlocks);

        foreach ($dayTasks as $task) {
            if (isset($counts[$task->time_range])) {
                $counts[$task->time_range]++;
            }
        }

        return $counts;
    }

    private function serializeSummary($task): array
    {
        return [
            'id' => $task->id,
            'time_range' => $task->time_range,
            'title' => $task->title,
            'status' => $task->status,
            'channel' => $task->channel
                ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
        ];
    }

    private function serializeDetail(VideoTask $task): array
    {
        return [
            'id' => $task->id,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'title' => $task->title,
            'script' => $task->script,
            'copy' => $task->copy,
            'key_phrases' => $task->key_phrases,
            'youtube_url' => $task->youtube_url,
            'status' => $task->status,
            'channel' => $task->channel
                ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
        ];
    }
}
