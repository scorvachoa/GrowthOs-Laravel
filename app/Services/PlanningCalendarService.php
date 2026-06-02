<?php

namespace App\Services;

use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlanningCalendarService
{
    public function __construct(
        private PeruHolidayService $holidays,
    ) {}

    public function snapshot(int $year, int $month, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? Carbon::today()->startOfWeek(Carbon::MONDAY);

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->addMonth();

        $monthTasks = VideoTask::query()
            ->where('task_date', '>=', $monthStart)
            ->where('task_date', '<', $monthEnd)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get()
            ->groupBy(fn (VideoTask $task) => $task->task_date->format('Y-m-d'));

        $tasksCount = [];
        $blocksMap = [];
        $tasksDetailMap = [];

        foreach ($monthTasks as $dayKey => $dayTasks) {
            $tasksCount[$dayKey] = $dayTasks->count();
            $blocksMap[$dayKey] = $this->buildBlockCounts($dayTasks);
            $tasksDetailMap[$dayKey] = $dayTasks
                ->map(fn (VideoTask $task) => $this->serializeSummary($task))
                ->values()
                ->all();
        }

        $weekEnd = $weekStart->copy()->addDays(7);
        $weekTasks = VideoTask::query()
            ->where('task_date', '>=', $weekStart)
            ->where('task_date', '<', $weekEnd)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $weekBlockMap = [];
        $weekTasksDetailMap = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $iso = $weekStart->copy()->addDays($offset)->format('Y-m-d');
            $weekBlockMap[$iso] = WorkBlocks::emptyCounts();
            $weekTasksDetailMap[$iso] = [];
        }

        foreach ($weekTasks as $task) {
            $iso = $task->task_date->format('Y-m-d');
            if (! isset($weekBlockMap[$iso])) {
                continue;
            }
            if (isset($weekBlockMap[$iso][$task->time_range])) {
                $weekBlockMap[$iso][$task->time_range]++;
            }
            $weekTasksDetailMap[$iso][] = $this->serializeSummary($task);
        }

        return [
            'year' => $year,
            'month' => $month,
            'today' => Carbon::today()->format('Y-m-d'),
            'week_start' => $weekStart->format('Y-m-d'),
            'tasks_count' => $tasksCount,
            'blocks_map' => $blocksMap,
            'week_blocks_map' => $weekBlockMap,
            'tasks_detail_map' => $tasksDetailMap,
            'week_tasks_detail_map' => $weekTasksDetailMap,
            'holidays_map' => $this->holidays->forYear($year),
            'work_blocks' => WorkBlocks::all(),
            'statuses' => VideoTaskStatuses::options(),
        ];
    }

    public function tasksForDate(string $date): array
    {
        return VideoTask::query()
            ->whereDate('task_date', $date)
            ->orderBy('time_range')
            ->get()
            ->map(fn (VideoTask $task) => $this->serializeDetail($task))
            ->values()
            ->all();
    }

    private function buildBlockCounts(Collection $dayTasks): array
    {
        $counts = WorkBlocks::emptyCounts();

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
