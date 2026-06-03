<?php

namespace App\Services;

use App\Models\Idea;

class IdeaService
{
    public function list(int $channelId, string $search = '', string $sort = 'date_desc')
    {
        $query = Idea::query()->where('channel_id', $channelId);

        if ($search = trim($search)) {
            $query->where('content', 'like', "%{$search}%");
        }

        $query->orderBy('is_used');

        match ($sort) {
            'alpha_asc' => $query->orderBy('content'),
            'alpha_desc' => $query->orderBy('content', 'desc'),
            'date_asc' => $query->orderBy('created_at'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query->get();
    }

    public function createBulk(int $channelId, array $lines): int
    {
        $lines = array_filter(array_map('trim', $lines));
        if (empty($lines)) {
            return 0;
        }

        $items = [];
        foreach ($lines as $line) {
            $items[] = ['channel_id' => $channelId, 'content' => $line];
        }

        Idea::insert($items);

        return count($items);
    }

    public function toggleUsed(Idea $idea, bool $value): void
    {
        $idea->update(['is_used' => $value]);
    }

    public function update(Idea $idea, array $data): void
    {
        $idea->update($data);
    }

    public function delete(Idea $idea): void
    {
        $idea->delete();
    }

    public function exportUnused(int $channelId): string
    {
        return Idea::query()
            ->where('channel_id', $channelId)
            ->where('is_used', false)
            ->orderBy('created_at')
            ->get()
            ->pluck('content')
            ->implode("\n");
    }
}
