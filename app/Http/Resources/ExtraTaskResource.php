<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtraTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'task_date' => $this->task_date->format('Y-m-d'),
            'time_range' => $this->time_range,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'location' => $this->location,
            'is_extra' => true,
            'created_by' => $this->created_by,
        ];

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
