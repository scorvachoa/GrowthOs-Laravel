<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Idea;
use App\Services\AI\AIContentService;
use App\Services\IdeaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IdeaController extends Controller
{
    public function __construct(
        protected IdeaService $ideaService,
        protected AIContentService $ai,
    ) {}

    public function index(Request $request)
    {
        $channels = Channel::query()->orderBy('name')->get();
        $channelId = (int) ($request->query('channel_id', $channels->first()?->id ?? 0));
        $search = $request->query('q', '');
        $sort = $request->query('sort', 'date_desc');
        $status = $request->query('status', 'all');

        if (! in_array($sort, ['date_desc', 'date_asc', 'alpha_asc', 'alpha_desc'])) {
            $sort = 'date_desc';
        }

        if (! in_array($status, ['all', 'used', 'pending'])) {
            $status = 'all';
        }

        if (! $channels->contains('id', $channelId)) {
            $channelId = $channels->first()?->id ?? 0;
        }

        $ideas = $channelId
            ? $this->ideaService->list($channelId, $search, $sort, $status)->withQueryString()
            : collect();

        return Inertia::render('Ideas/Index', [
            'channels' => $channels->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ]),
            'selected_channel_id' => $channelId,
            'ideas' => $ideas,
            'query' => $search,
            'sort' => $sort,
            'status' => $status,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where(fn ($q) => $q->where('organization_id', $request->user()->activeOrganizationId()))],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $channel = Channel::findOrFail($validated['channel_id']);
        $count = $validated['count'] ?? 10;

        $existing = Idea::query()
            ->where('channel_id', $channel->id)
            ->where('is_used', false)
            ->limit(60)
            ->pluck('content')
            ->all();

        try {
            $ideas = $this->ai->generateIdeas($channel->name, $existing, $count);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudieron generar ideas con IA. Inténtalo de nuevo.'], 502);
        }

        if ($ideas === []) {
            return response()->json(['error' => 'La IA no devolvió ideas nuevas. Inténtalo de nuevo.'], 422);
        }

        $created = $this->ideaService->createBulk($channel->id, $ideas);

        session()->flash('success', "{$created} ideas generadas con IA");

        return response()->json(['created' => $created], 201);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where(fn ($q) => $q->where('organization_id', $request->user()->activeOrganizationId()))],
            'content_lines' => ['required', 'string'],
        ]);

        $created = $this->ideaService->createBulk(
            $validated['channel_id'],
            explode("\n", $validated['content_lines'])
        );

        return redirect()->back()->with('success', "{$created} ideas creadas");
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where(fn ($q) => $q->where('organization_id', $request->user()->activeOrganizationId()))],
            'txt_file' => ['required', 'file', 'mimes:txt', 'max:2048'],
        ]);

        $text = $request->file('txt_file')->getContent();
        $created = $this->ideaService->createBulk(
            $validated['channel_id'],
            explode("\n", $text)
        );

        return redirect()->back()->with('success', "{$created} ideas importadas");
    }

    public function export(Request $request)
    {
        $request->validate(['channel_id' => ['required', 'integer', Rule::exists('channels', 'id')->where(fn ($q) => $q->where('organization_id', $request->user()->activeOrganizationId()))]]);

        $channelId = (int) $request->query('channel_id');
        $channel = Channel::findOrFail($channelId);
        $sanitized = preg_replace('/[\\\\\/:*?"<>|]/', '_', $channel->name);

        $text = $this->ideaService->exportIdeas($channelId);

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Ideas-'.$sanitized.'-'.date('Y-m-d').'.txt"',
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:65535'],
        ]);

        $idea = Idea::query()->findOrFail($id);

        $user = $request->user();
        if (! $user->hasRole(['Super Admin', 'Admin']) && $idea->created_by !== $user->id) {
            abort(403, 'No tienes permiso para editar esta idea');
        }

        $this->ideaService->update($idea, $validated);

        return redirect()->back()->with('success', 'Idea actualizada');
    }

    public function destroy($id)
    {
        $idea = Idea::query()->findOrFail($id);

        $user = request()->user();
        if (! $user->hasRole(['Super Admin', 'Admin']) && $idea->created_by !== $user->id) {
            abort(403, 'No tienes permiso para eliminar esta idea');
        }

        $this->ideaService->delete($idea);

        return redirect()->back()->with('success', 'Idea eliminada');
    }

    public function toggleUsed(Request $request, $id)
    {
        $validated = $request->validate(['used' => ['required', 'boolean']]);
        $idea = Idea::query()->findOrFail($id);

        $user = $request->user();
        if (! $user->hasRole(['Super Admin', 'Admin']) && $idea->created_by !== $user->id) {
            abort(403, 'No tienes permiso para modificar esta idea');
        }

        $this->ideaService->toggleUsed($idea, $validated['used']);

        return redirect()->back();
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:ideas,id'],
            'action' => ['required', 'string', 'in:mark_used,mark_pending,delete,edit'],
            'contents' => ['nullable', 'array'],
        ]);

        $ideas = Idea::whereIn('id', $validated['ids']);
        if (! $request->user()->hasRole('Super Admin') && ! $request->user()->hasRole('Admin')) {
            $ideas->where('created_by', $request->user()->id);
        }
        $ids = $ideas->pluck('id')->toArray();

        $count = $this->ideaService->bulkUpdate($ids, $validated['action'], $validated['contents'] ?? []);

        $messages = [
            'mark_used' => "{$count} ideas marcadas como usadas",
            'mark_pending' => "{$count} ideas marcadas como pendientes",
            'delete' => "{$count} ideas eliminadas",
            'edit' => "{$count} ideas actualizadas",
        ];

        return redirect()->back()->with('success', $messages[$validated['action']]);
    }
}
