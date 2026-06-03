<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = VideoTask::query()
            ->with('creator', 'channel')
            ->orderBy('task_date', 'desc')
            ->orderBy('time_range');

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $tasks = $query->paginate(30)->through(fn ($task) => [
            'id' => $task->id,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'title' => $task->title,
            'status' => $task->status,
            'channel' => $task->channel
                ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
            'created_by' => $task->creator?->name,
        ]);

        return Inertia::render('TaskHistory/Index', [
            'tasks' => $tasks,
            'statuses' => VideoTaskStatuses::options(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(VideoTask $videoTask)
    {
        $videoTask->load('creator', 'channel');

        $activities = $videoTask->activities()
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'causer' => $activity->causer?->name,
                'created_at' => $activity->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('TaskHistory/Show', [
            'task' => [
                'id' => $videoTask->id,
                'task_date' => $videoTask->task_date->format('Y-m-d'),
                'time_range' => $videoTask->time_range,
                'title' => $videoTask->title,
                'status' => $videoTask->status,
                'channel' => $videoTask->channel
                    ? ['name' => $videoTask->channel->name, 'color' => $videoTask->channel->color]
                    : null,
                'created_by' => $videoTask->creator?->name,
            ],
            'activities' => $activities,
        ]);
    }
}
