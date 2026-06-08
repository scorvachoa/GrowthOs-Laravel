<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'channel_id',
        'content',
        'is_used',
        'tags',
        'priority',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}
