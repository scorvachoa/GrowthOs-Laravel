<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VideoTaskController extends Controller
{
    public function create(Request $request)
    {
        $settings = Auth::user()->merged_settings;

        return Inertia::render('VideoTasks/Create', [
            'prefilled' => [
                'task_date' => $request->string('fecha', now()->format('Y-m-d')),
                'time_range' => $request->filled('bloque')
                    ? $this->resolveBlock($request->string('bloque')->toString())
                    : '',
            ],
            'statuses' => VideoTaskStatuses::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTask($request);

        $validated['created_by'] = auth()->id();

        VideoTask::create($validated);

        return redirect()
            ->route('planning.index', [
                'year' => Carbon::parse($validated['task_date'])->year,
                'month' => Carbon::parse($validated['task_date'])->month,
            ])
            ->with('success', 'Tarea creada correctamente.');
    }

    public function show(VideoTask $videoTask)
    {
        $videoTask->load('channel');
        return Inertia::render('VideoTasks/Show', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatuses::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function edit(VideoTask $videoTask)
    {
        $settings = Auth::user()->merged_settings;
        $videoTask->load('channel');
        return Inertia::render('VideoTasks/Edit', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatuses::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function update(Request $request, VideoTask $videoTask)
    {
        $validated = $this->validateTask($request, $videoTask->id);

        $videoTask->update($validated);

        return redirect()
            ->route('planning.index', [
                'year' => Carbon::parse($validated['task_date'])->year,
                'month' => Carbon::parse($validated['task_date'])->month,
            ])
            ->with('success', 'Tarea actualizada correctamente.');
    }

    public function destroy(VideoTask $videoTask)
    {
        $date = $videoTask->task_date;

        $videoTask->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('planning.index', [
                'year' => $date->year,
                'month' => $date->month,
            ])
            ->with('success', 'Tarea eliminada correctamente.');
    }

    public function updateStatus(Request $request, VideoTask $videoTask)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(VideoTaskStatuses::ALL)],
        ]);

        $videoTask->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'ok' => true,
            'status' => $videoTask->status,
        ]);
    }

    public function move(Request $request, VideoTask $videoTask)
    {
        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'];
        $blockHours = $settings['block_hours'];
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $workingDays = $settings['working_days'] ?? [1,2,3,4,5];

        $rules = [
            'task_date' => ['required', 'date'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $this->assertWorkingDay($validated['task_date'], $workingDays);

        if ($useBlocks) {
            $this->assertSlotAvailable(
                $validated['task_date'],
                $validated['time_range'],
                $videoTask->id
            );
        }

        $videoTask->update($validated);

        return response()->json([
            'ok' => true,
            'task_id' => $videoTask->id,
        ]);
    }

    private function validateTask(Request $request, ?int $exceptId = null): array
    {
        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'];
        $blockHours = $settings['block_hours'];
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $workingDays = $settings['working_days'] ?? [1,2,3,4,5];

        $rules = [
            'task_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'script' => ['nullable', 'string'],
            'copy' => ['nullable', 'string'],
            'youtube_url' => ['nullable', 'url'],
            'channel_id' => ['nullable', 'exists:channels,id'],
            'status' => ['required', Rule::in(VideoTaskStatuses::ALL)],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $this->assertWorkingDay($validated['task_date'], $workingDays);

        if ($useBlocks) {
            $this->assertSlotAvailable(
                $validated['task_date'],
                $validated['time_range'],
                $exceptId
            );
        }

        return $validated;
    }

    private function assertWorkingDay(string $date, array $workingDays): void
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso % 7;
        if (!in_array($dayOfWeek, $workingDays)) {
            throw ValidationException::withMessages([
                'task_date' => 'La fecha seleccionada no es un dia laborable.',
            ]);
        }
    }

    private function assertSlotAvailable(
        string $date,
        string $block,
        ?int $exceptId = null,
    ): void {
        $exists = VideoTask::query()
            ->whereDate('task_date', $date)
            ->where('time_range', $block)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'time_range' => 'Bloque ocupado en la fecha seleccionada.',
            ]);
        }
    }

    private function resolveBlock(string $block): string
    {
        $valid = WorkBlocks::fromSettings(Auth::user()->merged_settings);
        return in_array($block, $valid, true)
            ? $block
            : ($valid[0] ?? WorkBlocks::ALL[0]);
    }

    private function serializeTask(VideoTask $task): array
    {
        return [
            'id' => $task->id,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'title' => $task->title,
            'script' => $task->script,
            'copy' => $task->copy,
            'youtube_url' => $task->youtube_url,
            'status' => $task->status,
            'channel' => $task->relationLoaded('channel') && $task->channel
                ? ['id' => $task->channel->id, 'name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
        ];
    }
}
