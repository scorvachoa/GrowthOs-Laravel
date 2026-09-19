<?php

namespace App\Services;

use App\Models\Idea;
use Illuminate\Support\Facades\Auth;

class IdeaService
{
    private function scopeVisibleTo($query)
    {
        $user = Auth::user();
        if (! $user || $user->hasRole(['Super Admin', 'Admin'])) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    public function list(int $channelId, string $search = '', string $sort = 'date_desc', string $status = 'all', int $perPage = 50)
    {
        $query = Idea::query()->where('channel_id', $channelId);
        $this->scopeVisibleTo($query);

        if ($search = trim($search)) {
            $escaped = addcslashes($search, '%_');
            $query->where('content', 'like', "%{$escaped}%");
        }

        match ($status) {
            'used' => $query->where('is_used', true),
            'pending' => $query->where('is_used', false),
            default => null,
        };

        $query->orderBy('is_used');

        match ($sort) {
            'alpha_asc' => $query->orderBy('content'),
            'alpha_desc' => $query->orderBy('content', 'desc'),
            'date_asc' => $query->orderBy('created_at'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage);
    }

    public function createBulk(int $channelId, array $lines): int
    {
        $lines = array_filter(array_map('trim', $lines));
        if (empty($lines)) {
            return 0;
        }

        $now = now();
        $user = Auth::user();
        $orgId = $user?->activeOrganizationId();
        $data = array_map(fn ($line) => [
            'channel_id' => $channelId,
            'content' => $line,
            'organization_id' => $orgId,
            'created_by' => $user?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($lines));

        Idea::insert($data);

        return count($lines);
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

    public function bulkUpdate(array $ids, string $action, array $contents = []): int
    {
        if ($action === 'edit') {
            $ideas = Idea::query()->whereIn('id', $ids)->get()->keyBy('id');
            $count = 0;
            foreach ($contents as $id => $content) {
                if ($idea = $ideas->get((int) $id)) {
                    $idea->update(['content' => $content]);
                    $count++;
                }
            }

            return $count;
        }

        $ideas = Idea::query()->whereIn('id', $ids)->get();

        return match ($action) {
            'mark_used' => $ideas->each->update(['is_used' => true])->count(),
            'mark_pending' => $ideas->each->update(['is_used' => false])->count(),
            'delete' => $ideas->each->delete()->count(),
            default => 0,
        };
    }

    public function exportIdeas(int $channelId): string
    {
        $query = Idea::query()
            ->where('channel_id', $channelId)
            ->orderBy('created_at');
        $this->scopeVisibleTo($query);
        $ideas = $query->get();

        $unused = $ideas->where('is_used', false);
        $used = $ideas->where('is_used', true);

        $lines = [];

        if ($unused->isNotEmpty()) {
            $lines[] = 'Ideas pendientes';
            foreach ($unused as $idea) {
                $lines[] = '- '.$idea->content;
            }
        }

        if ($used->isNotEmpty()) {
            if ($lines) {
                $lines[] = '';
            }
            $lines[] = 'Ideas usadas';
            foreach ($used as $idea) {
                $lines[] = '- '.$idea->content;
            }
        }

        return implode("\n", $lines);
    }
}
