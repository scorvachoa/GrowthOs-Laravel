<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkSession extends Model
{
    use BelongsToOrganization, HasFactory, LogsActivity;

    protected $fillable = [
        'video_task_id',
        'date',
        'time_range',
        'status',
        'locale',
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
            ->logOnly(['date', 'time_range', 'status', 'locale'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function videoTask()
    {
        return $this->belongsTo(VideoTask::class);
    }
}
