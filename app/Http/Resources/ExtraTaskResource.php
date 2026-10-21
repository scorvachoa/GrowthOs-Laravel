<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\RendersShares;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtraTaskResource extends JsonResource
{
    use RendersShares;

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

        return $this->withShares($request, $data);
    }
}
