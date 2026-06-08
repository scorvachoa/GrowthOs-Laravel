<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idea' => $this->idea,
            'script' => $this->script,
            'copy_title' => $this->copy_title,
            'copy_description' => $this->copy_description,
            'copy_cta' => $this->copy_cta,
            'copy_hashtags' => $this->copy_hashtags,
            'copy_tags' => $this->copy_tags,
            'video_phrases' => $this->video_phrases,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'has_script' => !empty($this->script),
            'has_copy' => !empty($this->copy_title),
            'has_phrases' => !empty($this->video_phrases),
            'script_preview' => mb_substr($this->script, 0, 120),
        ];
    }
}
