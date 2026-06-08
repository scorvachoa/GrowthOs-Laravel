<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExtraTaskRequest;
use App\Http\Resources\ExtraTaskResource;
use App\Models\ExtraTask;
use App\Services\PlanningCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExtraTaskController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => ['required', 'date']]);

        return response()->json(
            ExtraTask::query()
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

        $task = ExtraTask::create($validated);
        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($task),
        ], 201);
    }

    public function update(StoreExtraTaskRequest $request, ExtraTask $extraTask)
    {
        $validated = $request->validated();

        $extraTask->update($validated);
        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($extraTask),
        ]);
    }

    public function destroy(ExtraTask $extraTask)
    {
        $extraTask->delete();
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    private function serialize(ExtraTask $task): array
    {
        return ExtraTaskResource::make($task)->resolve();
    }
}
