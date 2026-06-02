<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportHistory extends Model
{
    protected $fillable = [
        'user_id',
        'scope',
        'filename',
        'filters_json',
    ];

    protected $casts = [
        'filters_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
