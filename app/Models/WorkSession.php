<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class WorkSession extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'time_range', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function videoTask()
    {
        return $this->belongsTo(VideoTask::class);
    }
}
