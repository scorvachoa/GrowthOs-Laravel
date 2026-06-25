<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSession extends Model
{
    protected $fillable = [
        'video_task_id',
        'date',
        'time_range',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function videoTask()
    {
        return $this->belongsTo(VideoTask::class);
    }
}
