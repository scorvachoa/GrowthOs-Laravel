<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait TaskAuthorization
{
    private function ensureVisible($task): void
    {
        $user = Auth::user();
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return;
        }
        $isOwner = $task->created_by === $user->id;
        $isShared = $task->isSharedWithUser($user->id);
        $isPending = $task->isPendingForUser($user->id);
        if (! $isOwner && ! $isShared && ! $isPending) {
            abort(403, 'No tienes permiso para acceder a esta tarea');
        }
    }

    private function ensureCanEdit($task): void
    {
        $user = Auth::user();
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return;
        }
        if ($task->created_by === $user->id) {
            return;
        }
        $role = $task->shareRoleForUser($user->id);
        if ($role !== 'editor') {
            abort(403, 'No tienes permiso para editar esta tarea');
        }
    }
}
