<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtraTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_date' => $this->task_date->format('Y-m-d'),
            'time_range' => $this->time_range,
            'title' => $this->title,
            'status' => $this->status,
            'location' => $this->location,
            'is_extra' => true,
        ];
    }
}
