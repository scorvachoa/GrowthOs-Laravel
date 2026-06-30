<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class WorkSession extends Model
{
    use LogsActivity, BelongsToOrganization;

    protected $fillable = [
        'video_task_id',
        'date',
        'time_range',
        'status',
        'organization_id',
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
