<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DayObservation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'task_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date:Y-m-d',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
