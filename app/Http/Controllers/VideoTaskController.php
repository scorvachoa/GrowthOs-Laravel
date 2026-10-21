<?php

namespace App\Http\Controllers;

use App\Enums\VideoTaskStatus;
use App\Http\Requests\StoreVideoTaskRequest;
use App\Http\Resources\VideoTaskResource;
use App\Models\Channel;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Services\AI\AIContentService;
use App\Services\AI\DeepLService;
use App\Services\PlanningCalendarService;
use App\Services\PlanningValidator;
use App\Services\TaskShareService;
use App\Support\TimeRange;
use App\Support\WorkBlocks;
use App\Traits\TaskAuthorization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VideoTaskController extends Controller
{
    use TaskAuthorization;

    public function __construct(
        protected PlanningValidator $planningValidator,
        protected AIContentService $ai,
        protected DeepLService $deepl,
    ) {}

    public function generateCopy(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $script = $this->ensureUtf8(trim($validated['script']));

        try {
            $copy = $this->ai->generateCopy($script);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo generar el copy con IA. Inténtalo de nuevo.'], 502);
        }

        return response()->json([
            'copy' => [
                'title' => $this->ensureUtf8($copy['title'] ?? ''),
                'description' => $this->ensureUtf8($copy['description'] ?? ''),
                'cta' => $this->ensureUtf8($this->ensureCtaContacts($copy['cta'] ?? '')),
                'hashtags' => $this->ensureUtf8($this->formatHashtags($copy['hashtags'] ?? '')),
                'tags' => $this->ensureUtf8($copy['tags'] ?? ''),
            ],
        ], 201);
    }

    public function generateTitlesAndThumbnails(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'title' => ['nullable', 'string', 'max:300'],
        ]);

        $script = $this->ensureUtf8(trim($validated['script']));
        $title = $this->ensureUtf8(trim((string) ($validated['title'] ?? '')));

        try {
            $ideas = $this->ai->generateTitlesAndThumbnails($script, $title);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudieron generar títulos y thumbnails con IA. Inténtalo de nuevo.'], 502);
        }

        return response()->json([
            'titles' => array_values(array_filter(array_map(fn ($t) => $this->ensureUtf8($t), $ideas['titles'] ?? []))),
            'thumbnails' => array_values(array_filter(array_map(fn ($t) => $this->ensureUtf8($t), $ideas['thumbnails'] ?? []))),
        ], 201);
    }

    public function translate(Request $request)
    {
        $validated = $request->validate([
            'target_lang' => ['required', Rule::in($this->deepl->supportedTargets())],
            'title' => ['nullable', 'string', 'max:500'],
            'script' => ['nullable', 'string', 'max:10000'],
            'copy' => ['nullable', 'string', 'max:20000'],
        ]);

        $fields = array_filter([
            'title' => trim((string) ($validated['title'] ?? '')),
            'script' => trim((string) ($validated['script'] ?? '')),
            'copy' => trim((string) ($validated['copy'] ?? '')),
        ], fn ($value) => $value !== '');

        if ($fields === []) {
            return response()->json(['error' => 'No hay contenido para traducir.'], 422);
        }

        try {
            $translated = $this->deepl->translate(array_values($fields), $validated['target_lang']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo traducir con DeepL. Verifica DEEPL_API_KEY o inténtalo de nuevo.'], 502);
        }

        $result = array_fill_keys(array_keys($fields), '');
        foreach (array_keys($fields) as $index => $key) {
            $result[$key] = $translated[$index] ?? '';
        }

        return response()->json($result);
    }

    public function translateTask(Request $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);

        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'] ?? true;
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

        $rules = [
            'locale' => ['required', Rule::in($this->deepl->supportedTargets())],
            'channel_id' => ['required', Rule::exists('channels', 'id')->where(function ($q) {
                $q->where('organization_id', Auth::user()->activeOrganizationId());
            })],
            'task_date' => ['required', 'date'],
        ];

        if ($useBlocks) {
            $rules['time_range'] = ['required', Rule::in(WorkBlocks::fromSettings($settings))];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $channel = Channel::find($validated['channel_id']);
        if (! $channel || ! in_array($validated['locale'], $channel->locales ?: [], true)) {
            return response()->json(['error' => 'El canal seleccionado no corresponde al idioma elegido.'], 422);
        }

        if (! $useBlocks && ! TimeRange::endsAfterStart($validated['time_range'])) {
            return response()->json(['error' => TimeRange::INVALID_MESSAGE], 422);
        }

        $this->planningValidator->assertWorkingDay($validated['task_date'], $workingDays);

        if ($useBlocks) {
            $this->planningValidator->assertSlotAvailable(
                $validated['task_date'],
                $validated['time_range']
            );
        }

        try {
            $translated = $this->translateTaskFields($videoTask, $validated['locale']);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 502);
        }

        $groupId = $videoTask->translation_group_id ?: (string) Str::uuid();
        if (! $videoTask->translation_group_id) {
            $videoTask->update(['translation_group_id' => $groupId]);
        }

        $task = VideoTask::create([
            'task_date' => $validated['task_date'],
            'time_range' => $validated['time_range'],
            'title' => $translated['title'] !== '' ? $translated['title'] : $videoTask->title,
            'script' => ($translated['script'] ?? '') !== '' ? $translated['script'] : null,
            'copy' => ($translated['copy'] ?? '') !== '' ? $translated['copy'] : null,
            'youtube_url' => null,
            'status' => 'pending',
            'created_by' => Auth::id(),
            'channel_id' => $validated['channel_id'],
            'locale' => $validated['locale'],
            'key_phrases' => null,
            'is_pending' => false,
            'translation_group_id' => $groupId,
            'translated_from_task_id' => $videoTask->id,
        ]);

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task_id' => $task->id,
            'redirect' => '/tasks/'.$task->id,
        ], 201);
    }

    public function translateContent(Request $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);

        $validated = $request->validate([
            'locale' => ['required', Rule::in($this->deepl->supportedTargets())],
        ]);

        if (trim((string) $videoTask->title) === '') {
            return response()->json(['error' => 'No hay contenido para traducir.'], 422);
        }

        try {
            $translated = $this->translateTaskFields($videoTask, $validated['locale']);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json($translated);
    }

    public function translateCandidates(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'channel_id' => ['nullable', 'integer'],
        ]);

        $search = trim($validated['q'] ?? '');

        $tasks = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('title', 'like', "%{$search}%")
                    ->orWhere('script', 'like', "%{$search}%");
            }))
            ->when($validated['channel_id'] ?? null, fn ($q, $channelId) => $q->where('channel_id', $channelId))
            ->with('channel:id,name,color')
            ->orderByDesc('task_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'title', 'task_date', 'time_range', 'status', 'channel_id', 'locale'])
            ->map(fn (VideoTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'task_date' => $task->task_date->format('Y-m-d'),
                'time_range' => $task->time_range,
                'status' => $task->status,
                'status_label' => VideoTaskStatus::labelFor($task->status),
                'locale' => $task->locale ?: 'es',
                'channel' => $task->channel
                    ? ['id' => $task->channel->id, 'name' => $task->channel->name, 'color' => $task->channel->color]
                    : null,
            ])
            ->values()
            ->all();

        return response()->json(['results' => $tasks]);
    }

    private function translateTaskFields(VideoTask $videoTask, string $locale): array
    {
        $fields = array_filter([
            'title' => trim((string) $videoTask->title),
            'script' => trim((string) ($videoTask->script ?? '')),
            'copy' => trim((string) ($videoTask->copy ?? '')),
        ], fn ($value) => $value !== '');

        if ($fields === []) {
            throw new \RuntimeException('No hay contenido para traducir.');
        }

        try {
            $translated = $this->deepl->translate(array_values($fields), $locale);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo traducir con DeepL. Verifica DEEPL_API_KEY o inténtalo de nuevo.', 0, $e);
        }

        $result = array_fill_keys(array_keys($fields), '');
        foreach (array_keys($fields) as $index => $key) {
            $result[$key] = $translated[$index] ?? '';
        }

        return $result;
    }

    private function sessionLocales(): array
    {
        return $this->deepl->supportedTargets();
    }

    private function resolveTaskLocale(?string $channelId, ?string $locale): string
    {
        if (! $channelId) {
            return $locale ?: 'es';
        }

        $channel = Channel::find((int) $channelId);
        $locales = $channel?->locales ?: ['es'];

        if (! $locale) {
            return $locales[0] ?? 'es';
        }

        if (! in_array($locale, $locales, true)) {
            throw ValidationException::withMessages([
                'locale' => 'El idioma elegido no está disponible en el canal seleccionado.',
            ]);
        }

        return $locale;
    }

    private function ensureCtaContacts(string $cta): string
    {
        $cta = trim($cta);
        $url = trim((string) config('ai.business_url'));
        $phone = trim((string) config('ai.business_phone'));
        $lines = $cta !== '' ? [$cta] : [];

        if ($url !== '' && ! str_contains($cta, $url)) {
            $lines[] = "👉 {$url}";
        }

        if ($phone !== '' && (stripos($cta, 'whatsapp') === false || ! str_contains($cta, $phone))) {
            $lines[] = "📲 WhatsApp: {$phone}";
        }

        return implode("\n", array_filter($lines));
    }

    private function formatHashtags(string $hashtags): string
    {
        $parts = preg_split('/[\s,]+/', trim($hashtags)) ?: [];
        $tags = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (! str_starts_with($part, '#')) {
                $part = '#'.$part;
            }
            $tags[] = $part;
        }

        return implode(' ', array_unique($tags));
    }

    private function ensureUtf8(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $converted === false ? '' : $converted;
    }

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
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color', 'locales']),
        ]);
    }

    public function store(StoreVideoTaskRequest $request, TaskShareService $shareService)
    {
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        $translatedFrom = $validated['translated_from_task_id'] ?? null;
        unset($validated['shared_user_ids'], $validated['shared_roles'], $validated['translated_from_task_id']);

        $validated['locale'] = $this->resolveTaskLocale($validated['channel_id'] ?? null, $validated['locale'] ?? null);

        $task = VideoTask::create($validated);

        if ($translatedFrom) {
            $source = VideoTask::find($translatedFrom);
            if (! $source || (int) $source->organization_id !== (int) auth()->user()->activeOrganizationId()) {
                throw ValidationException::withMessages(['translated_from_task_id' => 'La tarea origen no existe.']);
            }
            $this->linkTranslationSource($task, $source);
        }

        if ($sharedUserIds !== null) {
            $shareService->sync($task, $sharedUserIds, $sharedRoles);
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

        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $orgId = $user->activeOrganizationId();

        $query = VideoTask::query()
            ->withoutGlobalScope('organization')
            ->where('is_pending', false);
        if (! $isSuperAdmin) {
            $query->where('organization_id', $orgId);
        }

        $prevTask = $query->clone()
            ->where(function ($q) use ($videoTask) {
                $q->where('task_date', '<', $videoTask->task_date)
                    ->orWhere(function ($q2) use ($videoTask) {
                        $q2->where('task_date', $videoTask->task_date)
                            ->where(function ($q3) use ($videoTask) {
                                $q3->where('time_range', '<', $videoTask->time_range)
                                    ->orWhere(function ($q4) use ($videoTask) {
                                        $q4->where('time_range', $videoTask->time_range)
                                            ->where('id', '<', $videoTask->id);
                                    });
                            });
                    });
            })
            ->orderByDesc('task_date')
            ->orderByDesc('time_range')
            ->orderByDesc('id')
            ->first(['id', 'title']);

        $nextTask = $query->clone()
            ->where(function ($q) use ($videoTask) {
                $q->where('task_date', '>', $videoTask->task_date)
                    ->orWhere(function ($q2) use ($videoTask) {
                        $q2->where('task_date', $videoTask->task_date)
                            ->where(function ($q3) use ($videoTask) {
                                $q3->where('time_range', '>', $videoTask->time_range)
                                    ->orWhere(function ($q4) use ($videoTask) {
                                        $q4->where('time_range', $videoTask->time_range)
                                            ->where('id', '>', $videoTask->id);
                                    });
                            });
                    });
            })
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->orderBy('id')
            ->first(['id', 'title']);

        return Inertia::render('VideoTasks/Show', [
            'task' => $this->serializeTask($videoTask),
            'statuses' => VideoTaskStatus::options(),
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color', 'locales']),
            'activities' => $activities,
            'translation_history' => $this->translationHistory($videoTask),
            'prev_task' => $prevTask ? ['id' => $prevTask->id, 'title' => $prevTask->title] : null,
            'next_task' => $nextTask ? ['id' => $nextTask->id, 'title' => $nextTask->title] : null,
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
            'channels' => Channel::query()->orderBy('name')->get(['id', 'name', 'color', 'locales']),
        ]);
    }

    public function update(StoreVideoTaskRequest $request, VideoTask $videoTask, TaskShareService $shareService)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);
        $validated = $request->validated();

        $sharedUserIds = $validated['shared_user_ids'] ?? null;
        $sharedRoles = $validated['shared_roles'] ?? [];
        unset($validated['shared_user_ids'], $validated['shared_roles']);

        $channelId = array_key_exists('channel_id', $validated) ? $validated['channel_id'] : $videoTask->channel_id;
        $locale = array_key_exists('locale', $validated) ? $validated['locale'] : $videoTask->locale;
        $validated['locale'] = $this->resolveTaskLocale($channelId, $locale);

        $videoTask->update($validated);

        if ($sharedUserIds !== null) {
            $shareService->sync($videoTask, $sharedUserIds, $sharedRoles);
        }

        PlanningCalendarService::bustCache();

        return redirect()
            ->route('tasks.show', $videoTask->id)
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
            'youtube_url' => ['nullable', 'url'],
        ]);

        $data = ['status' => $validated['status']];
        if ($request->has('youtube_url')) {
            $data['youtube_url'] = $validated['youtube_url'] ?: null;
        }

        $videoTask->update($data);
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
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

        $rules = [
            'task_date' => ['required', 'date'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::fromSettings($settings);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        if (! $useBlocks && isset($validated['time_range']) && ! TimeRange::endsAfterStart($validated['time_range'])) {
            return back()->withErrors(['time_range' => TimeRange::INVALID_MESSAGE])->withInput();
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
            'locale' => ['nullable', 'string', Rule::in($this->sessionLocales())],
        ]);

        if ($validated['time_range'] ?? null) {
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
            'locale' => $validated['locale'] ?? ($videoTask->locale ?: 'es'),
        ]);

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'time_range' => $session->time_range,
                'status' => $session->status,
                'locale' => $session->locale,
            ],
        ]);
    }

    public function updateSession(Request $request, VideoTask $videoTask, WorkSession $session)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'after_or_equal:'.$videoTask->task_date->format('Y-m-d')],
            'time_range' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'youtube_url' => ['nullable', 'url'],
            'locale' => ['nullable', 'string', Rule::in($this->sessionLocales())],
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

        $session->update([
            'date' => $validated['date'] ?? $session->date,
            'time_range' => $validated['time_range'] ?? $session->time_range,
            'status' => $validated['status'],
            'locale' => $validated['locale'] ?? $session->locale,
        ]);

        if ($request->has('youtube_url')) {
            $videoTask->update(['youtube_url' => $validated['youtube_url'] ?: null]);
        }

        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'time_range' => $session->time_range,
                'status' => $session->status,
                'locale' => $session->locale,
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
        $currentUserId = Auth::id();

        $tasks = VideoTask::query()
            ->visibleTo()
            ->where('is_pending', true)
            ->with('channel', 'shares.sharedByUser')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (VideoTask $task) use ($currentUserId) {
                $myShare = $task->shares->firstWhere('shared_with_user_id', $currentUserId);

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'original_date' => $task->task_date->format('Y-m-d'),
                    'time_range' => $task->time_range,
                    'channel_id' => $task->channel_id,
                    'created_by' => $task->created_by,
                    'channel' => $task->channel ? ['id' => $task->channel->id, 'name' => $task->channel->name, 'color' => $task->channel->color] : null,
                    'shared_by_user_name' => $myShare?->sharedByUser?->name,
                    'created_at' => $task->created_at->format('Y-m-d'),
                ];
            });

        return response()->json($tasks);
    }

    public function todaySummary()
    {
        return response()->json([
            'today' => $this->todayTasksQuery()->count(),
            'overdue' => $this->overdueTasksQuery()->count(),
        ]);
    }

    public function todayTasks()
    {
        $today = $this->todayTasksQuery()
            ->with('channel:id,name,color')
            ->orderBy('time_range')
            ->get(['id', 'title', 'task_date', 'time_range', 'status', 'channel_id'])
            ->map(fn ($task) => $this->serializeSummaryTask($task));

        $overdue = $this->overdueTasksQuery()
            ->with('channel:id,name,color')
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get(['id', 'title', 'task_date', 'time_range', 'status', 'channel_id'])
            ->map(fn ($task) => array_merge($this->serializeSummaryTask($task), [
                'days_overdue' => (int) round(abs(Carbon::today()->diffInDays($task->task_date))),
            ]));

        return response()->json([
            'today' => $today,
            'overdue' => $overdue,
        ]);
    }

    private function todayTasksQuery()
    {
        $today = Carbon::today();

        return VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->where('task_date', '>=', $today)
            ->where('task_date', '<', $today->copy()->addDay());
    }

    private function overdueTasksQuery()
    {
        return VideoTask::query()
            ->visibleTo()
            ->where('is_pending', false)
            ->overdue();
    }

    private function serializeSummaryTask(VideoTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'task_date' => $task->task_date->format('Y-m-d'),
            'time_range' => $task->time_range,
            'status' => $task->status,
            'status_label' => VideoTaskStatus::labelFor($task->status),
            'channel' => $task->channel
                ? ['name' => $task->channel->name, 'color' => $task->channel->color]
                : null,
        ];
    }

    public function moveToPending(VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $videoTask->update(['is_pending' => true]);
        $videoTask->activities()->latest()->first()?->update(['description' => 'Movido a pendiente']);
        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    public function restorePending(Request $request, VideoTask $videoTask)
    {
        $this->ensureVisible($videoTask);
        $this->ensureCanEdit($videoTask);

        $settings = Auth::user()->merged_settings;
        $useBlocks = $settings['use_blocks'];
        $workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5];

        $rules = [
            'task_date' => ['required', 'date', 'after_or_equal:today'],
        ];

        if ($useBlocks) {
            $blocks = WorkBlocks::fromSettings($settings);
            $rules['time_range'] = ['required', Rule::in($blocks)];
        } else {
            $rules['time_range'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        if (! $useBlocks && isset($validated['time_range']) && ! TimeRange::endsAfterStart($validated['time_range'])) {
            return response()->json(['errors' => ['time_range' => [TimeRange::INVALID_MESSAGE]]], 422);
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
        $videoTask->activities()->latest()->first()?->update(['description' => 'Restaurado desde pendiente']);
        PlanningCalendarService::bustCache();

        return response()->json([
            'ok' => true,
            'task_id' => $videoTask->id,
        ]);
    }

    private function linkTranslationSource(VideoTask $task, VideoTask $source): void
    {
        $groupId = $source->translation_group_id ?: (string) Str::uuid();

        if (! $source->translation_group_id) {
            $source->update(['translation_group_id' => $groupId]);
        }

        $task->update([
            'translation_group_id' => $groupId,
            'translated_from_task_id' => $source->id,
        ]);
    }

    private function translationHistory(VideoTask $task): array
    {
        if (! $task->translation_group_id && ! $task->translated_from_task_id) {
            return [];
        }

        $query = VideoTask::query()
            ->visibleTo()
            ->with('channel:id,name,color')
            ->orderBy('task_date')
            ->orderBy('id');

        if ($task->translation_group_id) {
            $query->where('translation_group_id', $task->translation_group_id);
        } else {
            $query->where(function ($q) use ($task) {
                $q->where('id', $task->translated_from_task_id)
                    ->orWhere('translated_from_task_id', $task->id);
            });
        }

        return $query->get()
            ->map(fn (VideoTask $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'status_label' => VideoTaskStatus::labelFor($t->status),
                'task_date' => $t->task_date->format('Y-m-d'),
                'time_range' => $t->time_range,
                'locale' => $t->locale ?: 'es',
                'channel_name' => $t->channel?->name,
                'channel_color' => $t->channel?->color,
                'youtube_url' => $t->youtube_url,
                'is_original' => $t->translated_from_task_id === null,
                'is_self' => $t->id === $task->id,
            ])
            ->values()
            ->all();
    }

    private function serializeTask(VideoTask $task): array
    {
        return VideoTaskResource::make($task->load('sessions'))->resolve();
    }
}
