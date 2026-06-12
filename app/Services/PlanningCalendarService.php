<?php

namespace App\Services;

use App\Models\ExtraTask;
use App\Models\VideoTask;
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
        Cache::increment('planning_bust_' . ($userId ?? Auth::id()));
    }

    public function snapshot(int $year, int $month, ?Carbon $weekStart = null, ?array $workBlocks = null): array
    {
        $workBlocks ??= WorkBlocks::all();
        $weekStart = $weekStart ?? Carbon::today()->startOfWeek(Carbon::MONDAY);

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->addMonth();

        $monthTasks = $this->loadMonthTasks($monthStart, $monthEnd);
        $monthExtraTasks = $this->loadMonthExtraTasks($monthStart, $monthEnd);

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
            'holidays_map' => $this->holidays->forYear($year),
            'work_blocks' => $workBlocks,
            'statuses' => VideoTaskStatus::options(),
        ];
    }

    private function loadMonthTasks(Carbon $start, Carbon $end): Collection
    {
        return VideoTask::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get()
            ->groupBy(fn (VideoTask $task) => $task->task_date->format('Y-m-d'));
    }

    private function loadMonthExtraTasks(Carbon $start, Carbon $end): Collection
    {
        return ExtraTask::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->get()
            ->groupBy(fn (ExtraTask $task) => $task->task_date->format('Y-m-d'));
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
                ->map(fn (VideoTask $task) => $this->serializeSummary($task))
                ->values()
                ->all();
        }

        return [$tasksCount, $blocksMap, $tasksDetailMap];
    }

    private function buildExtraTasksMap(Collection $extraTasks): array
    {
        $map = [];
        foreach ($extraTasks as $dayKey => $_) {
            $map[$dayKey] = true;
        }
        return $map;
    }

    private function buildWeekMaps(Carbon $weekStart, array $workBlocks): array
    {
        $weekEnd = $weekStart->copy()->addDays(7);

        $weekTasks = VideoTask::query()
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
            $iso = $task->task_date->format('Y-m-d');
            if (!isset($weekBlockMap[$iso])) continue;
            if (isset($weekBlockMap[$iso][$task->time_range])) {
                $weekBlockMap[$iso][$task->time_range]++;
            }
            $weekTasksDetailMap[$iso][] = $this->serializeSummary($task);
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
        return VideoTask::query()
            ->where('task_date', '>=', $date)
            ->where('task_date', '<', Carbon::parse($date)->addDay())
            ->orderBy('time_range')
            ->get()
            ->map(fn (VideoTask $task) => $this->serializeDetail($task))
            ->values()
            ->all();
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

    private function serializeSummary(VideoTask $task): array
    {
        return [
            'id' => $task->id,
            'time_range' => $task->time_range,
            'title' => $task->title,
            'status' => $task->status,
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
        ];
    }
}
