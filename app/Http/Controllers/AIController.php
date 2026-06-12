<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateScriptRequest;
use App\Http\Resources\GeneratedVideoResource;
use App\Models\Channel;
use App\Models\GeneratedVideo;
use App\Models\VideoTask;
use App\Services\AI\AIContentService;
use App\Services\PlanningCalendarService;
use App\Services\PlanningValidator;
use App\Enums\VideoTaskStatus;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AIController extends Controller
{
    public function __construct(
        protected PlanningValidator $planningValidator,
    ) {}

    protected function sharedData(): array
    {
        return [
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'work_blocks' => WorkBlocks::fromSettings(request()->user()->merged_settings ?? []),
            'statuses' => VideoTaskStatus::options(),
        ];
    }

    public function index()
    {
        $recent = GeneratedVideo::query()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'idea' => $v->idea,
                'has_script' => !empty($v->script),
                'has_copy' => !empty($v->copy_title),
                'has_phrases' => !empty($v->video_phrases),
                'created_at' => $v->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('AI/Index', array_merge(
            ['recent' => $recent],
            $this->sharedData(),
        ));
    }

    public function show(int $id)
    {
        $video = GeneratedVideo::findOrFail($id);
        return response()->json(
            GeneratedVideoResource::make($video)->resolve()
        );
    }

    public function destroy(int $id)
    {
        $video = GeneratedVideo::findOrFail($id);
        $video->delete();
        return response()->json(['ok' => true]);
    }

    public function history(Request $request)
    {
        $query = GeneratedVideo::query()
            ->orderBy('created_at', 'desc');

        if ($search = $request->get('search')) {
            $query->where('idea', 'like', "%{$search}%");
        }

        if ($request->get('has_script')) {
            $query->whereNotNull('script')->where('script', '!=', '');
        }

        if ($request->get('has_copy')) {
            $query->where(function ($q) {
                $q->whereNotNull('copy_title')->where('copy_title', '!=', '');
            });
        }

        if ($request->get('has_phrases')) {
            $query->whereNotNull('video_phrases')->where('video_phrases', '!=', '');
        }

        if ($request->get('used_in_planner') === '1') {
            $query->where('used_in_planner', true);
        } elseif ($request->get('used_in_planner') === '0') {
            $query->where('used_in_planner', false);
        }

        $videos = $query->paginate(20)
            ->through(fn ($v) => [
                'id' => $v->id,
                'idea' => $v->idea,
                'script_preview' => mb_substr($v->script, 0, 120),
                'has_script' => !empty($v->script),
                'has_copy' => !empty($v->copy_title),
                'has_phrases' => !empty($v->video_phrases),
                'used_in_planner' => $v->used_in_planner,
                'created_at' => $v->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('AI/History', array_merge(
            ['videos' => $videos, 'filters' => [
                'search' => $search,
                'has_script' => $request->get('has_script'),
                'has_copy' => $request->get('has_copy'),
                'has_phrases' => $request->get('has_phrases'),
                'used_in_planner' => $request->get('used_in_planner'),
            ]],
            $this->sharedData(),
        ));
    }

    public function downloadTxt(int $id)
    {
        $video = GeneratedVideo::findOrFail($id);

        $lines = [
            'IDEA',
            $video->idea ?: 'Sin idea.',
            '',
            'GUION',
            $video->script ?: 'Sin guion.',
            '',
            'COPY',
            'Titulo: ' . ($video->copy_title ?: 'Sin titulo.'),
            '',
            'Descripcion',
            $video->copy_description ?: 'Sin descripcion.',
            '',
            'CTA',
            $video->copy_cta ?: '',
            '',
            'Hashtags',
            $video->copy_hashtags ?: '',
            '',
            'Tags',
            $video->copy_tags ?: '',
            '',
            'FRASES',
            $video->video_phrases ?: 'Sin frases.',
        ];

        $content = implode("\n", $lines);
        $filename = str($video->idea)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(50)->toString() ?: 'video';

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.txt"',
        ]);
    }

    public function generateScript(GenerateScriptRequest $request, AIContentService $ai)
    {
        $idea = trim($request->validated()['idea']);

        $video = GeneratedVideo::create([
            'idea' => $idea,
            'status' => 'processing',
            'script' => '',
        ]);

        try {
            $script = $ai->generateScript($idea);
            $video->update(['script' => $script, 'status' => 'completed']);
            $video->refresh();
        } catch (\Throwable $e) {
            $video->update(['status' => 'failed']);
            report($e);

            return response()->json([
                'message' => 'Error al generar el guion. Verifica la conexion con Gemini.',
            ], 500);
        }

        return response()->json([
            'video_id' => $video->id,
            'idea' => $idea,
            'script' => $video->script,
            'status' => $video->status,
        ], 201);
    }

    public function createTask(Request $request)
    {
        $settings = auth()->user()->merged_settings;
        $blockHours = $settings['block_hours'] ?? 2;
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $useBlocks = $settings['use_blocks'] ?? true;
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

        $rules = [
            'generated_video_id' => ['required', 'integer', 'exists:generated_videos,id'],
            'task_date' => ['required', 'date'],
            'status' => ['required', Rule::in(VideoTaskStatus::values())],
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $this->planningValidator->assertWorkingDay($validated['task_date'], $workingDays);
        $this->planningValidator->assertSlotAvailable($validated['task_date'], $validated['time_range']);

        $video = GeneratedVideo::findOrFail($validated['generated_video_id']);
        $video->update(['used_in_planner' => true]);

        $copy = collect([
            'title' => $video->copy_title,
            'description' => $video->copy_description,
            'cta' => $video->copy_cta,
            'hashtags' => $video->copy_hashtags,
            'tags' => $video->copy_tags,
        ])->filter()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $task = VideoTask::create([
            'task_date' => $validated['task_date'],
            'time_range' => $validated['time_range'],
            'title' => $video->idea,
            'script' => $video->script,
            'copy' => $copy ?: null,
            'status' => $validated['status'],
            'created_by' => auth()->id(),
            'channel_id' => $validated['channel_id'] ?: null,
        ]);
        PlanningCalendarService::bustCache();

        $date = Carbon::parse($task->task_date);

        return response()->json([
            'ok' => true,
            'task_id' => $task->id,
            'redirect' => route('planning.index', ['year' => $date->year, 'month' => $date->month]),
        ], 201);
    }
}
