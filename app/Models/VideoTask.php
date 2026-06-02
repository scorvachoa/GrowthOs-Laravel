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
        'key_phrases',
        'youtube_url',
        'status',
        'created_by',
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
}