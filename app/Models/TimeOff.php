<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeOff extends Model
{
    protected $table = 'time_off';

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_time' => 'string',
            'end_time' => 'string',
            'approved_at' => 'datetime',
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
