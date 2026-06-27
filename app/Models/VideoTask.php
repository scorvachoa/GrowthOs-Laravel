<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class VideoTask extends Model
{
    use SoftDeletes, LogsActivity, BelongsToOrganization;

    protected $fillable = [
        'task_date',
        'time_range',
        'title',
        'script',
        'copy',
        'translations',
        'youtube_url',
        'status',
        'created_by',
        'channel_id',
        'key_phrases',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'translations' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'script', 'copy', 'translations', 'key_phrases', 'status', 'time_range', 'task_date', 'channel_id', 'youtube_url'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function sessions()
    {
        return $this->hasMany(WorkSession::class);
    }
}