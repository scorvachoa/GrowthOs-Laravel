<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'use_blocks',
        'block_hours',
        'show_youtube_chart',
    ];

    protected function casts(): array
    {
        return [
            'use_blocks' => 'boolean',
            'block_hours' => 'integer',
            'show_youtube_chart' => 'boolean',
        ];
    }
}
