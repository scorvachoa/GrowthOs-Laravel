<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraTaskRequest;
use App\Http\Resources\ExtraTaskResource;
use App\Models\ExtraTask;
use App\Services\PlanningCalendarService;
use App\Services\TaskShareService;
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

    public function store(StoreExtraTaskRequest $request, TaskShareService $shareService)
    {
        $validated = $request->validated();

        $validated['created_by'] = auth()->id();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $task = ExtraTask::create($validated);

        if ($sharedUserIds !== null) {
            $shareService->sync($task, $sharedUserIds, $sharedRoles);
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($task),
        ], 201);
    }

    public function update(StoreExtraTaskRequest $request, ExtraTask $extraTask, TaskShareService $shareService)
    {
        $this->ensureVisible($extraTask);
        $this->ensureCanEdit($extraTask);
        $validated = $request->validated();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $extraTask->update($validated);

        if ($sharedUserIds !== null) {
            $shareService->sync($extraTask, $sharedUserIds, $sharedRoles);
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
