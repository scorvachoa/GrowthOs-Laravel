<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\GeneratedVideo;
use App\Models\VideoTask;
use App\Services\AI\AIContentService;
use App\Services\AI\ElevenLabsService;
use App\Support\VideoTaskStatuses;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AIController extends Controller
{
    public function __construct(
        protected AIContentService $aiContentService,
        protected ElevenLabsService $elevenLabsService,
    ) {}

    protected function sharedData(): array
    {
        return [
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color']),
            'work_blocks' => WorkBlocks::fromSettings(request()->user()->merged_settings ?? []),
            'statuses' => VideoTaskStatuses::options(),
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
        return response()->json([
            'id' => $video->id,
            'idea' => $video->idea,
            'script' => $video->script,
            'copy_title' => $video->copy_title,
            'copy_description' => $video->copy_description,
            'copy_cta' => $video->copy_cta,
            'copy_hashtags' => $video->copy_hashtags,
            'copy_tags' => $video->copy_tags,
            'video_phrases' => $video->video_phrases,
            'created_at' => $video->created_at?->format('Y-m-d H:i'),
        ]);
    }

    public function destroy(int $id)
    {
        $video = GeneratedVideo::findOrFail($id);
        $video->delete();
        return response()->json(['ok' => true]);
    }

    public function history()
    {
        $videos = GeneratedVideo::query()
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($v) => [
                'id' => $v->id,
                'idea' => $v->idea,
                'script_preview' => mb_substr($v->script, 0, 120),
                'has_script' => !empty($v->script),
                'has_copy' => !empty($v->copy_title),
                'has_phrases' => !empty($v->video_phrases),
                'created_at' => $v->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('AI/History', array_merge(
            ['videos' => $videos],
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

    public function createTask(Request $request)
    {
        $settings = Auth::user()->merged_settings;
        $blockHours = $settings['block_hours'] ?? 2;
        $startHour = WorkBlocks::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = WorkBlocks::parseHour($settings['default_work_end'] ?? '18:00');
        $useBlocks = $settings['use_blocks'] ?? true;
        $workingDays = $settings['working_days'] ?? [1,2,3,4,5];

        $rules = [
            'generated_video_id' => ['required', 'integer', 'exists:generated_videos,id'],
            'task_date' => ['required', 'date'],
            'status' => ['required', Rule::in(VideoTaskStatuses::ALL)],
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::generate($blockHours, $startHour, $endHour);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $this->assertWorkingDay($validated['task_date'], $workingDays);
        $this->assertSlotAvailable($validated['task_date'], $validated['time_range']);

        $video = GeneratedVideo::findOrFail($validated['generated_video_id']);

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

        $date = Carbon::parse($task->task_date);

        return response()->json([
            'ok' => true,
            'task_id' => $task->id,
            'redirect' => route('planning.index', ['year' => $date->year, 'month' => $date->month]),
        ], 201);
    }

    public function generateScript(Request $request)
    {
        $validated = $request->validate([
            'idea' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $idea = trim($validated['idea']);
        $script = $this->aiContentService->generateScript($idea);

        $video = GeneratedVideo::create([
            'idea' => $idea,
            'script' => $script,
            'copy_title' => '',
            'copy_description' => '',
            'copy_cta' => '',
            'copy_hashtags' => '',
            'copy_tags' => '',
            'video_phrases' => '',
        ]);

        return response()->json([
            'video_id' => $video->id,
            'idea' => $idea,
            'script' => $script,
        ], 201);
    }

    public function generateAudio(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'idea' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'integer', 'exists:generated_videos,id'],
        ]);

        $idea = trim($validated['idea'] ?? '');
        $script = trim($validated['script']);

        $video = null;
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
        }

        if ($video === null) {
            $video = GeneratedVideo::create([
                'idea' => $idea ?: 'Guion editado manualmente',
                'script' => $script,
            ]);
        }

        $response = $this->elevenLabsService->generateAudio($script);

        $filename = $this->safeAudioFilename($idea ?: 'guion-audio');

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, "{$filename}.mp3", [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => "attachment; filename=\"{$filename}.mp3\"",
        ]);
    }

    public function generateCopy(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'idea' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'integer', 'exists:generated_videos,id'],
        ]);

        $idea = trim($validated['idea'] ?? '');
        $script = trim($validated['script']);

        $copy = $this->aiContentService->generateCopy($script);

        $video = null;
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
        }

        if ($video === null) {
            $video = GeneratedVideo::create([
                'idea' => $idea ?: 'Guion editado manualmente',
                'script' => $script,
                'copy_title' => $copy['title'],
                'copy_description' => $copy['description'],
                'copy_cta' => $copy['cta'],
                'copy_hashtags' => $copy['hashtags'],
                'copy_tags' => $copy['tags'],
            ]);
        } else {
            $video->update([
                'copy_title' => $copy['title'],
                'copy_description' => $copy['description'],
                'copy_cta' => $copy['cta'],
                'copy_hashtags' => $copy['hashtags'],
                'copy_tags' => $copy['tags'],
            ]);
        }

        return response()->json([
            'video_id' => $video->id,
            'idea' => $video->idea,
            'script' => $video->script,
            'copy' => [
                'title' => $copy['title'],
                'description' => $copy['description'],
                'cta' => $copy['cta'],
                'hashtags' => $copy['hashtags'],
                'tags' => $copy['tags'],
            ],
        ], 201);
    }

    public function generatePhrases(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'idea' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'integer', 'exists:generated_videos,id'],
        ]);

        $idea = trim($validated['idea'] ?? '');
        $script = trim($validated['script']);

        $phrases = $this->aiContentService->generatePhrases($script);

        $video = null;
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
        }

        if ($video === null) {
            $video = GeneratedVideo::create([
                'idea' => $idea ?: 'Guion editado manualmente',
                'script' => $script,
                'video_phrases' => $phrases,
            ]);
        } else {
            $video->update([
                'video_phrases' => $phrases,
            ]);
        }

        return response()->json([
            'video_id' => $video->id,
            'idea' => $video->idea,
            'script' => $video->script,
            'phrases' => $phrases,
        ], 201);
    }

    public function generateCopyPhrases(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'idea' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'integer', 'exists:generated_videos,id'],
        ]);

        $idea = trim($validated['idea'] ?? '');
        $script = trim($validated['script']);

        $copy = $this->aiContentService->generateCopy($script);
        $phrases = $this->aiContentService->generatePhrases($script);

        $video = null;
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
        }

        if ($video === null) {
            $video = GeneratedVideo::create([
                'idea' => $idea ?: 'Guion editado manualmente',
                'script' => $script,
                'copy_title' => $copy['title'],
                'copy_description' => $copy['description'],
                'copy_cta' => $copy['cta'],
                'copy_hashtags' => $copy['hashtags'],
                'copy_tags' => $copy['tags'],
                'video_phrases' => $phrases,
            ]);
        } else {
            $video->update([
                'copy_title' => $copy['title'],
                'copy_description' => $copy['description'],
                'copy_cta' => $copy['cta'],
                'copy_hashtags' => $copy['hashtags'],
                'copy_tags' => $copy['tags'],
                'video_phrases' => $phrases,
            ]);
        }

        return response()->json([
            'video_id' => $video->id,
            'idea' => $video->idea,
            'script' => $video->script,
            'copy' => [
                'title' => $copy['title'],
                'description' => $copy['description'],
                'cta' => $copy['cta'],
                'hashtags' => $copy['hashtags'],
                'tags' => $copy['tags'],
            ],
            'phrases' => $phrases,
        ], 201);
    }

    private function safeAudioFilename(string $idea): string
    {
        $base = preg_replace('/[^a-zA-Z0-9]+/', '-', trim($idea));
        $base = strtolower(trim($base, '-'));
        return mb_substr($base, 0, 50) ?: 'guion-audio';
    }

    private function assertWorkingDay(string $date, array $workingDays): void
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso % 7;
        if (!in_array($dayOfWeek, $workingDays)) {
            throw ValidationException::withMessages([
                'task_date' => 'La fecha seleccionada no es un dia laborable.',
            ]);
        }
    }

    private function assertSlotAvailable(string $date, string $block, ?int $exceptId = null): void
    {
        $exists = VideoTask::query()
            ->whereDate('task_date', $date)
            ->where('time_range', $block)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'time_range' => 'Bloque ocupado en la fecha seleccionada.',
            ]);
        }
    }
}
