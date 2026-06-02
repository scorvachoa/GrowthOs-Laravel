<?php

namespace App\Http\Controllers;

use App\Models\ExtraTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExtraTaskController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => ['required', 'date']]);

        return response()->json(
            ExtraTask::query()
                ->whereDate('task_date', $request->string('fecha'))
                ->orderBy('time_range')
                ->get()
                ->map(fn (ExtraTask $task) => $this->serialize($task))
                ->values()
                ->all()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_date' => ['required', 'date'],
            'time_range' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:24'],
            'location' => ['required', Rule::in(['oficina', 'fuera'])],
        ]);

        $validated['created_by'] = auth()->id();

        $task = ExtraTask::create($validated);

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($task),
        ], 201);
    }

    public function update(Request $request, ExtraTask $extraTask)
    {
        $validated = $request->validate([
            'task_date' => ['required', 'date'],
            'time_range' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:24'],
            'location' => ['required', Rule::in(['oficina', 'fuera'])],
        ]);

        $extraTask->update($validated);

        return response()->json([
            'ok' => true,
            'task' => $this->serialize($extraTask),
        ]);
    }

    public function destroy(ExtraTask $extraTask)
    {
        $extraTask->delete();

        return response()->json(['ok' => true]);
    }

    private function serialize(ExtraTask $task): array
    {
        return [
            'id' => $task->id,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'title' => $task->title,
            'status' => $task->status,
            'location' => $task->location,
        ];
    }
}
