<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'channel_id',
        'content',
        'is_used',
        'tags',
        'priority',
        'category',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'priority' => 'integer',
        ];
    }
}
