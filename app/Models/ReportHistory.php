<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReportHistory extends Model
{
    use BelongsToOrganization;

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

    public function fileExists(): bool
    {
        return $this->filename && Storage::disk('public')->exists('reports/' . $this->filename);
    }
}
