<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedVideo extends Model
{
    protected $fillable = [
        'idea',
        'script',
        'copy_title',
        'copy_description',
        'copy_cta',
        'copy_hashtags',
        'copy_tags',
        'video_phrases',
    ];
}
