<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use Illuminate\Http\Request;

use App\Models\VideoTask;

class VideoTaskController extends Controller
{
    public function index()
{
    $tasks = VideoTask::query()
        ->orderBy('task_date')
        ->get()
        ->groupBy(function ($task) {

            return $task->task_date
                ->format('Y-m-d');

        });

    return Inertia::render(
        'VideoTasks/Index',
        [
            'tasks' => $tasks,
        ]
    );
}

    public function create()
    {
        return Inertia::render(
            'VideoTasks/Create'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_date' => [
                'required',
                'date',
            ],

            'time_range' => [
                'required',
                'max:255',
            ],

            'title' => [
                'required',
                'max:255',
            ],

            'script' => [
                'nullable',
            ],

            'copy' => [
                'nullable',
            ],

            'key_phrases' => [
                'nullable',
            ],

            'youtube_url' => [
                'nullable',
                'url',
            ],

            'status' => [
                'required',
            ],
        ]);

        $validated['created_by'] =
            auth()->id();

        VideoTask::create(
            $validated
        );

        return redirect()
            ->route('video-tasks.index')
            ->with(
                'success',
                'Task created successfully.'
            );
    }
}