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
        ];

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

        return $data;
    }
}
