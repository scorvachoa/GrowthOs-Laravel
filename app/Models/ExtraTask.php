<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\SharedWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraTask extends Model
{
    use BelongsToOrganization, SharedWithUser, SoftDeletes;

    protected $fillable = [
        'task_date',
        'time_range',
        'title',
        'description',
        'status',
        'location',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
