<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraTaskRequest;
use App\Http\Resources\ExtraTaskResource;
use App\Models\ExtraTask;
use App\Models\User;
use App\Notifications\TaskSharedNotification;
use App\Services\PlanningCalendarService;
use App\Traits\TaskAuthorization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExtraTaskController extends Controller
{
    use TaskAuthorization;
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ExtraTask::class);

        $request->validate(['fecha' => ['required', 'date']]);

        return response()->json(
            ExtraTask::query()
                ->visibleTo()
                ->with('shares.sharedByUser')
                ->where('task_date', '>=', $request->string('fecha'))
                ->where('task_date', '<', Carbon::parse($request->string('fecha'))->addDay())
                ->orderBy('time_range')
                ->get()
                ->map(fn (ExtraTask $task) => $this->serialize($task))
                ->values()
                ->all()
        );
    }

    public function store(StoreExtraTaskRequest $request)
    {
        $validated = $request->validated();

        $validated['created_by'] = auth()->id();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $task = ExtraTask::create($validated);

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
                        auth()->user(), $task, 'extra_task', $share
                    ));
                }
            }
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($task),
        ], 201);
    }

    public function update(StoreExtraTaskRequest $request, ExtraTask $extraTask)
    {
        $this->ensureVisible($extraTask);
        $this->ensureCanEdit($extraTask);
        $validated = $request->validated();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $extraTask->update($validated);

        if ($sharedUserIds !== null) {
            $existing = $extraTask->shares()->pluck('shared_with_user_id')->toArray();
            $extraTask->shares()->where('shared_by_user_id', auth()->id())->delete();
            foreach ($sharedUserIds as $idx => $userId) {
                if ((int) $userId === auth()->id()) {
                    continue;
                }
                $role = $sharedRoles[$idx] ?? 'editor';
                $extraTask->shares()->create([
                    'shared_with_user_id' => $userId,
                    'shared_by_user_id' => auth()->id(),
                    'role' => $role,
                ]);
                if (! in_array((int) $userId, $existing)) {
                    $recipient = User::find($userId);
                    if ($recipient) {
                        $share = $extraTask->shares()->where('shared_with_user_id', $userId)->first();
                        $recipient->notify(new TaskSharedNotification(
                            auth()->user(), $extraTask, 'extra_task', $share
                        ));
                    }
                }
            }
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($extraTask),
        ]);
    }

    public function destroy(ExtraTask $extraTask)
    {
        $this->ensureVisible($extraTask);

        if ($extraTask->created_by !== auth()->id() && ! auth()->user()->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Solo el creador puede eliminar esta tarea');
        }

        $extraTask->delete();
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    private function serialize(ExtraTask $task): array
    {
        return ExtraTaskResource::make($task)->resolve();
    }
}
