<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait AuthorizesAbsences
{
    protected function canManageAbsences(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin']);
    }

    protected function ensureAbsenceApprovable(object $absence): User
    {
        $user = request()->user();

        if (! $user->hasRole('Super Admin') && ! $this->canManageOrg($user, $absence->user?->organization_id)) {
            abort(403);
        }

        return $user;
    }

    protected function ensureAbsenceModifiable(object $absence): User
    {
        $user = request()->user();

        if ($absence->user_id !== $user->id && ! $user->hasRole('Super Admin') && ! $this->canManageOrg($user, $absence->user?->organization_id)) {
            abort(403);
        }

        return $user;
    }

    private function canManageOrg(User $user, ?int $orgId): bool
    {
        return $orgId && $user->activeOrganizationId() === $orgId && $this->canManageAbsences($user);
    }
}
