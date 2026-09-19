<?php

namespace App\Http\Controllers;

use App\Enums\VideoTaskStatus;
use App\Http\Requests\StoreVideoTaskRequest;
use App\Http\Resources\VideoTaskResource;
use App\Models\Channel;
use App\Models\User;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Notifications\TaskSharedNotification;
use App\Services\PlanningCalendarService;
use App\Services\PlanningValidator;
use App\Support\WorkBlocks;
use App\Traits\TaskAuthorization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VideoTaskController extends Controller
{
    use TaskAuthorization;
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

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $task = VideoTask::create($validated);

        if ($sharedUserIds !== null) {
            foreach ($sharedUserIds as $idx => $userId) {
                if ((int) $userId === auth()->id()) {
                    continue;
                }
                $role = $sharedRoles[$idx] ?? 'editor';
                $share = $task->shares()->create([
                    'shared_with_user_id' => $userId,
                    'shared_by_user_id' => auth()->id(),
                    'role' => $role,
                ]);
                $recipient = User::find($userId);
                if ($recipient) {
                    $recipient->notify(new TaskSharedNotification(
                        auth()->user(), $task, 'video_task', $share
                    ));
                }
            }
        }

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
        $this->ensureVisible($videoTask);
        $videoTask->load('channel', 'shares.sharedByUser', 'shares.sharedWithUser');

        $activities = $videoTask->activities()
            ->with('causer')
            ->latest()
            ->take(30)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'causer_name' => $a->causer?->name ?? 'Sistema',
                'created_at' => $a->created_at->format('d/m/Y H:i'),
                'properties' => $a->changes,
            ]);

        return Inertia::render('VideoTasks/Show', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'activities' => $activities,
        ]);
    }

    public function edit(VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $settings = Auth::user()->merged_settings;
        $videoTask->load('channel', 'shares.sharedByUser');

        return Inertia::render('VideoTasks/Edit', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function update(StoreVideoTaskRequest $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);
        $validated = $request->validated();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $videoTask->update($validated);

        if ($sharedUserIds !== null) {
            $existing = $videoTask->shares()->pluck('shared_with_user_id')->toArray();
            $videoTask->shares()->where('shared_by_user_id', auth()->id())->delete();
            foreach ($sharedUserIds as $idx => $userId) {
                if ((int) $userId === auth()->id()) {
                    continue;
                }
                $role = $sharedRoles[$idx] ?? 'editor';
                $videoTask->shares()->create([
                    'shared_with_user_id' => $userId,
                    'shared_by_user_id' => auth()->id(),
                    'role' => $role,
                ]);
                if (! in_array((int) $userId, $existing)) {
                    $recipient = User::find($userId);
                    if ($recipient) {
                        $share = $videoTask->shares()->where('shared_with_user_id', $userId)->first();
                        $recipient->notify(new TaskSharedNotification(
                            auth()->user(), $videoTask, 'video_task', $share
                        ));
                    }
                }
            }
        }

        PlanningCalendarService::bustCache();

        return redirect()
            ->route('planning.index', [
                'year' => Carbon::parse($validated['task_date'])->year,
                'month' => Carbon::parse($validated['task_date'])->month,
            ])
            ->with('success', 'Tarea actualizada correctamente.');
    }

    public function destroy(VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);

        if ($videoTask->created_by !== Auth::id() && ! Auth::user()->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Solo el creador puede eliminar esta tarea');
        }

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
            ->with('success', 'Tarea eliminada correctamente.');
    }

    public function updateStatus(Request $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

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
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'];
        $blockHours = $settings['block_hours'];
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

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

        if (! $useBlocks && isset($validated['time_range']) && str_contains($validated['time_range'], '-')) {
            [$start, $end] = explode('-', $validated['time_range'], 2);
            if (strlen($start) === 5 && strlen($end) === 5 && $end <= $start) {
                return back()->withErrors(['time_range' => 'La hora fin debe ser mayor a la hora de inicio'])->withInput();
            }
        }

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
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$videoTask->task_date->format('Y-m-d')],
            'time_range' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['in_progress', 'completed'])],
        ]);

        if ($validated['time_range']) {
            $this->planningValidator->assertSlotAvailable(
                $validated['date'],
                $validated['time_range'],
                $videoTask->id
            );
        }

        $session = $videoTask->sessions()->create([
            'date' => $validated['date'],
            'time_range' => $validated['time_range'] ?? null,
            'status' => $validated['status'] ?? 'in_progress',
        ]);

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
            'date' => ['nullable', 'date', 'after_or_equal:'.$videoTask->task_date->format('Y-m-d')],
            'time_range' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
        ]);

        $timeRange = $validated['time_range'] ?? null;

        if ($timeRange) {
            $this->planningValidator->assertSlotAvailable(
                $validated['date'] ?? $session->date->format('Y-m-d'),
                $timeRange,
                $videoTask->id,
                $session->id
            );
        }

        $session->update($validated);

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
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $session->delete();
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    public function pending()
    {
        $tasks = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', true)
            ->with('channel')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (VideoTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'original_date' => $task->task_date->format('Y-m-d'),
                'time_range' => $task->time_range,
                'channel' => $task->channel ? ['name' => $task->channel->name, 'color' => $task->channel->color] : null,
                'created_at' => $task->created_at->format('Y-m-d'),
            ]);

        return response()->json($tasks);
    }

    public function moveToPending(VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $videoTask->update(['is_pending' => true]);
        $videoTask->activity()->latest()->first()?->update(['description' => 'Movido a pendiente']);
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    public function restorePending(Request $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'];
        $blockHours = $settings['block_hours'];
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

        $rules = [
            'task_date' => ['required', 'date', 'after_or_equal:today'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        if (! $useBlocks && isset($validated['time_range']) && str_contains($validated['time_range'], '-')) {
            [$start, $end] = explode('-', $validated['time_range'], 2);
            if (strlen($start) === 5 && strlen($end) === 5 && $end <= $start) {
                return response()->json(['errors' => ['time_range' => ['La hora fin debe ser mayor a la hora de inicio']]], 422);
            }
        }

        $this->planningValidator->assertWorkingDay($validated['task_date'], $workingDays);

        if ($useBlocks) {
            $this->planningValidator->assertSlotAvailable(
                $validated['task_date'],
                $validated['time_range'],
                $videoTask->id
            );
        }

        $videoTask->update([
            'task_date' => $validated['task_date'],
            'time_range' => $validated['time_range'],
            'is_pending' => false,
        ]);
        $videoTask->activity()->latest()->first()?->update(['description' => 'Restaurado desde pendiente']);
        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task_id' => $videoTask->id,
        ]);
    }

    private function serializeTask(VideoTask $task): array
    {
        return VideoTaskResource::make($task->load('sessions'))->resolve();
    }
}
