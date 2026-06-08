<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\IdeaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IdeaController extends Controller
{
    public function __construct(
        protected IdeaService $ideaService
    ) {}

    public function index(Request $request)
    {
        $channels = Channel::query()->orderBy('name')->get();
        $channelId = (int) ($request->query('channel_id', $channels->first()?->id ?? 0));
        $search = $request->query('q', '');
        $sort = $request->query('sort', 'date_desc');

        if (!in_array($sort, ['date_desc', 'date_asc', 'alpha_asc', 'alpha_desc'])) {
            $sort = 'date_desc';
        }

        if (!$channels->contains('id', $channelId)) {
            $channelId = $channels->first()?->id ?? 0;
        }

        $ideas = $channelId
            ? $this->ideaService->list($channelId, $search, $sort)
            : collect();

        return Inertia::render('Ideas/Index', [
            'channels' => $channels->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ]),
            'selected_channel_id' => $channelId,
            'ideas' => $ideas->map(fn ($idea) => [
                'id' => $idea->id,
                'content' => $idea->content,
                'is_used' => $idea->is_used,
                'tags' => $idea->tags,
                'priority' => $idea->priority,
                'category' => $idea->category,
                'created_at' => $idea->created_at?->format('Y-m-d') ?? '',
            ]),
            'query' => $search,
            'sort' => $sort,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
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
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
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
        $request->validate(['channel_id' => ['required', 'integer', 'exists:channels,id']]);

        $channelId = (int) $request->query('channel_id');
        $channel = Channel::findOrFail($channelId);
        $sanitized = preg_replace('/[\\\\\/:*?"<>|]/', '_', $channel->name);

        $text = $this->ideaService->exportIdeas($channelId);

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Ideas-' . $sanitized . '-' . date('Y-m-d') . '.txt"',
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:65535'],
        ]);

        $idea = \App\Models\Idea::query()->findOrFail($id);
        $this->ideaService->update($idea, $validated);

        return redirect()->back()->with('warning', 'Idea actualizada');
    }

    public function destroy($id)
    {
        $idea = \App\Models\Idea::query()->findOrFail($id);
        $this->ideaService->delete($idea);

        return redirect()->back()->with('error', 'Idea eliminada');
    }

    public function toggleUsed(Request $request, $id)
    {
        $validated = $request->validate(['used' => ['required', 'boolean']]);
        $idea = \App\Models\Idea::query()->findOrFail($id);
        $this->ideaService->toggleUsed($idea, $validated['used']);

        return redirect()->back();
    }
}
