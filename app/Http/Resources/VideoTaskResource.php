<?php

namespace App\Http\Resources;

use App\Enums\VideoTaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'task_date' => $this->task_date->format('Y-m-d'),
            'time_range' => $this->time_range,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => VideoTaskStatus::labels()[$this->status] ?? $this->status,
            'script' => $this->script,
            'copy' => $this->copy,
            'key_phrases' => $this->key_phrases,
            'youtube_url' => $this->youtube_url,
            'translations' => $this->translations,
            'created_by' => $this->created_by,
        ];

        if ($this->relationLoaded('sessions')) {
            $data['sessions'] = $this->sessions->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date->format('Y-m-d'),
                'time_range' => $s->time_range,
                'status' => $s->status,
            ]);
        }

        if ($this->relationLoaded('channel') && $this->channel) {
            $data['channel'] = [
                'id' => $this->channel->id,
                'name' => $this->channel->name,
                'color' => $this->channel->color,
            ];
        }

        if ($this->relationLoaded('creator') && $this->creator) {
            $data['creator'] = [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ];
        }

        if ($this->relationLoaded('shares')) {
            $data['shared_user_ids'] = $this->shares->pluck('shared_with_user_id')->toArray();
            $currentUserId = $request->user()?->id;
            $myShare = $this->shares->firstWhere('shared_with_user_id', $currentUserId);
            if ($myShare) {
                $data['shared_by_user_name'] = $myShare->sharedByUser?->name;
            }
            $data['shared_with_users'] = $this->shares->map(fn ($s) => [
                'id' => $s->shared_with_user_id,
                'name' => $s->sharedWithUser?->name,
                'accepted' => $s->isAccepted(),
                'role' => $s->role,
            ])->values()->all();
        }

        return $data;
    }
}
