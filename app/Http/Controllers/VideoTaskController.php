<?php

namespace App\Http\Controllers;

use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VideoTaskController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('VideoTasks/Create', [
            'prefilled' => [
                'task_date' => $request->string('fecha', now()->format('Y-m-d')),
                'time_range' => $this->resolveBlock(
                    $request->string('bloque')->toString()
                ),
            ],
            'work_blocks' => WorkBlocks::all(),
            'statuses' => VideoTaskStatuses::options(),
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
        return Inertia::render('VideoTasks/Show', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatuses::options(),
        ]);
    }

    public function edit(VideoTask $videoTask)
    {
        return Inertia::render('VideoTasks/Edit', [
            'task' => $this->serializeTask($videoTask),
            'work_blocks' => WorkBlocks::all(),
            'statuses' => VideoTaskStatuses::options(),
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
        $validated = $request->validate([
            'task_date' => ['required', 'date'],
            'time_range' => ['required', Rule::in(WorkBlocks::ALL)],
        ]);

        $this->assertNotSunday($validated['task_date']);
        $this->assertSlotAvailable(
            $validated['task_date'],
            $validated['time_range'],
            $videoTask->id
        );

        $videoTask->update($validated);

        return response()->json([
            'ok' => true,
            'task_id' => $videoTask->id,
        ]);
    }

    private function validateTask(Request $request, ?int $exceptId = null): array
    {
        $validated = $request->validate([
            'task_date' => ['required', 'date'],
            'time_range' => ['required', Rule::in(WorkBlocks::ALL)],
            'title' => ['required', 'string', 'max:255'],
            'script' => ['nullable', 'string'],
            'copy' => ['nullable', 'string'],
            'key_phrases' => ['nullable', 'string'],
            'youtube_url' => ['nullable', 'url'],
            'status' => ['required', Rule::in(VideoTaskStatuses::ALL)],
        ]);

        $this->assertNotSunday($validated['task_date']);
        $this->assertSlotAvailable(
            $validated['task_date'],
            $validated['time_range'],
            $exceptId
        );

        return $validated;
    }

    private function assertNotSunday(string $date): void
    {
        if (Carbon::parse($date)->isSunday()) {
            throw ValidationException::withMessages([
                'task_date' => 'No se permiten tareas en domingo.',
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
        return WorkBlocks::isValid($block)
            ? $block
            : WorkBlocks::ALL[0];
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
            'key_phrases' => $task->key_phrases,
            'youtube_url' => $task->youtube_url,
            'status' => $task->status,
        ];
    }
}
