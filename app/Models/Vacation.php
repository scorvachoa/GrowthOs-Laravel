<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Vacation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'type',
        'status',
        'days_used',
        'year',
        'reason',
        'approved_by',
        'approved_at',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'days_used' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
