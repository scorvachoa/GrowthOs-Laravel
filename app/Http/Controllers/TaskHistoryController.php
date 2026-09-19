<?php

namespace App\Http\Controllers;

use App\Enums\VideoTaskStatus;
use App\Models\VideoTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = VideoTask::query()
            ->visibleTo()
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

        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);

        $tasks = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($task) => [
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
            'statuses' => VideoTaskStatus::options(),
            'filters' => $request->only(['q', 'status', 'per_page']),
        ]);
    }

    public function show(VideoTask $videoTask)
    {
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

        $serialize = fn ($task) => [
            'id' => $task->id,
            'title' => $task->title,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'status' => $task->status,
            'script' => $task->script,
            'copy' => $task->copy,
            'youtube_url' => $task->youtube_url,
            'key_phrases' => $task->key_phrases,
            'created_by' => $task->created_by,
            'channel' => $task->channel ? [
                'id' => $task->channel->id,
                'name' => $task->channel->name,
                'color' => $task->channel->color,
            ] : null,
            'translations' => $task->translations ?? [],
            'sessions' => $task->sessions->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date,
                'time_range' => $s->time_range,
                'status' => $s->status,
            ]),
            'shared_by_user_name' => $task->shares->first()?->sharedByUser?->name,
            'shared_with_users' => $task->shares->map(fn ($s) => [
                'id' => $s->sharedWithUser->id,
                'name' => $s->sharedWithUser->name,
                'accepted' => $s->isAccepted(),
                'role' => $s->role,
            ]),
        ];

        return Inertia::render('VideoTasks/Show', [
            'task' => $serialize($videoTask),
            'statuses' => \App\Enums\VideoTaskStatus::options(),
            'channels' => \App\Models\Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'activities' => $activities,
        ]);
    }
}
