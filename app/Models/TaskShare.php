<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskShare extends Model
{
    protected $table = 'task_sharings';

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'shared_with_user_id',
        'shared_by_user_id',
        'accepted_at',
        'role',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function shareable()
    {
        return $this->morphTo();
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function sharedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    public function isReader(): bool
    {
        return $this->role === 'reader';
    }
}
