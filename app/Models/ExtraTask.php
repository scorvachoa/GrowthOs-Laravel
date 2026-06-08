<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraTask extends Model
{
    use SoftDeletes, BelongsToOrganization;

    protected $fillable = [
        'task_date',
        'time_range',
        'title',
        'status',
        'location',
        'created_by',
    ];

    protected $casts = [
        'task_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
