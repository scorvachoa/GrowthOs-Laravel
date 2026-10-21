<?php

namespace App\Http\Resources;

use App\Enums\VideoTaskStatus;
use App\Http\Resources\Concerns\RendersShares;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoTaskResource extends JsonResource
{
    use RendersShares;

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'task_date' => $this->task_date->format('Y-m-d'),
            'time_range' => $this->time_range,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => VideoTaskStatus::labelFor($this->status),
            'script' => $this->script,
            'copy' => $this->copy,
            'notes' => $this->notes,
            'key_phrases' => $this->key_phrases,
            'youtube_url' => $this->youtube_url,
            'locale' => $this->locale ?: 'es',
            'created_by' => $this->created_by,
        ];

        if ($this->relationLoaded('sessions')) {
            $data['sessions'] = $this->sessions->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date->format('Y-m-d'),
                'time_range' => $s->time_range,
                'status' => $s->status,
                'locale' => $s->locale,
            ]);
        }

        if ($this->relationLoaded('channel') && $this->channel) {
            $data['channel'] = [
                'id' => $this->channel->id,
                'name' => $this->channel->name,
                'color' => $this->channel->color,
                'locales' => $this->channel->locales ?: ['es'],
            ];
        }

        return $this->withShares($request, $data);
    }
}
