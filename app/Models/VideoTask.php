<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'task_date',
        'time_range',
        'title',
        'script',
        'copy',
        'youtube_url',
        'status',
        'created_by',
        'channel_id',
    ];

    protected $casts = [
        'task_date' => 'date',
    ];

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
}