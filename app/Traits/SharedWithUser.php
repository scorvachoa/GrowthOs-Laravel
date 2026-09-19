<?php

namespace App\Traits;

use App\Models\TaskShare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait SharedWithUser
{
    /**
     * Scope: visible tasks for the current user.
     * - Admin / Super Admin: all org tasks
     * - Employee: own tasks + tasks shared with them
     */
    public function scopeVisibleTo(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();
        $user = Auth::user();

        if (! $userId || ! $user) {
            return $query;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return $query;
        }

        $modelClass = get_class($this);

        return $query->where(function (Builder $q) use ($userId) {
            $q->where('created_by', $userId)
                ->orWhereHas('shares', function (Builder $sq) use ($userId) {
                    $sq->where('shared_with_user_id', $userId)
                        ->whereNotNull('accepted_at');
                });
        });
    }

    public function shares()
    {
        return $this->morphMany(TaskShare::class, 'shareable');
    }

    public function sharedUserIds(): array
    {
        return $this->shares()->pluck('shared_with_user_id')->toArray();
    }

    public function isSharedWithUser(int $userId): bool
    {
        return $this->shares()
            ->where('shared_with_user_id', $userId)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    public function isPendingForUser(int $userId): bool
    {
        return $this->shares()
            ->where('shared_with_user_id', $userId)
            ->whereNull('accepted_at')
            ->exists();
    }

    public function shareRoleForUser(int $userId): ?string
    {
        $share = $this->shares()
            ->where('shared_with_user_id', $userId)
            ->whereNotNull('accepted_at')
            ->first();

        return $share?->role;
    }

    public function canEditForUser(int $userId): bool
    {
        if ($this->created_by === $userId) {
            return true;
        }

        if (Auth::user() && Auth::user()->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        $role = $this->shareRoleForUser($userId);

        return $role === 'editor';
    }
}
