<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoTaskRequest;
use App\Http\Resources\VideoTaskResource;
use App\Models\Channel;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Services\PlanningCalendarService;
use App\Services\PlanningValidator;
use App\Enums\VideoTaskStatus;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VideoTaskController extends Controller
{
    public function __construct(
        protected PlanningValidator $planningValidator,
    ) {}

    public function create(Request $request)
    {
        $settings = Auth::user()->merged_settings;

        return Inertia::render('VideoTasks/Create', [
            'prefilled' => [
                'task_date' => $request->string('fecha', now()->format('Y-m-d')),
                'time_range' => $request->filled('bloque')
                    ? $this->planningValidator->resolveBlock($settings, $request->string('bloque')->toString())
                    : '',
            ],
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreVideoTaskRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        VideoTask::create($validated);

        PlanningCalendarService::bustCache();

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
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function edit(VideoTask $videoTask)
    {
        $settings = Auth::user()->merged_settings;
        $videoTask->load('channel');
        return Inertia::render('VideoTasks/Edit', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function update(StoreVideoTaskRequest $request, VideoTask $videoTask)
    {
        $validated = $request->validated();

        $videoTask->update($validated);
        PlanningCalendarService::bustCache();

        return redirect()
            ->route('planning.index', [
                'year' => Carbon::parse($validated['task_date'])->year,
                'month' => Carbon::parse($validated['task_date'])->month,
            ])
            ->with('warning', 'Tarea actualizada correctamente.');
    }

    public function destroy(VideoTask $videoTask)
    {
        $date = $videoTask->task_date;

        $videoTask->delete();
        PlanningCalendarService::bustCache();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('planning.index', [
                'year' => $date->year,
                'month' => $date->month,
            ])
            ->with('error', 'Tarea eliminada correctamente.');
    }

    public function updateStatus(Request $request, VideoTask $videoTask)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(VideoTaskStatus::values())],
        ]);

        $videoTask->update([
            'status' => $validated['status'],
        ]);
        PlanningCalendarService::bustCache();

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

        $this->planningValidator->assertWorkingDay($validated['task_date'], $workingDays);

        if ($useBlocks) {
            $this->planningValidator->assertSlotAvailable(
                $validated['task_date'],
                $validated['time_range'],
                $videoTask->id
            );
        }

        $videoTask->update($validated);
        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task_id' => $videoTask->id,
        ]);
    }

    public function storeSession(Request $request, VideoTask $videoTask)
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:' . $videoTask->task_date->format('Y-m-d')],
            'time_range' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['in_progress', 'completed'])],
        ]);

        $session = $videoTask->sessions()->create([
            'date' => $validated['date'],
            'time_range' => $validated['time_range'] ?? null,
            'status' => $validated['status'] ?? 'in_progress',
        ]);

        if ($session->status === 'completed') {
            $videoTask->update(['status' => 'completed']);
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'time_range' => $session->time_range,
                'status' => $session->status,
            ],
        ]);
    }

    public function updateSession(Request $request, VideoTask $videoTask, WorkSession $session)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'after_or_equal:' . $videoTask->task_date->format('Y-m-d')],
            'time_range' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
        ]);

        $session->update($validated);

        if ($validated['status'] === 'completed') {
            $videoTask->update(['status' => 'completed']);
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'time_range' => $session->time_range,
                'status' => $session->status,
            ],
        ]);
    }

    public function destroySession(VideoTask $videoTask, WorkSession $session)
    {
        $session->delete();
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    private function serializeTask(VideoTask $task): array
    {
        return VideoTaskResource::make($task->load('sessions'))->resolve();
    }
}
