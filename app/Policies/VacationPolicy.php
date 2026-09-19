<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacation;

class VacationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view vacations');
    }

    public function view(User $user, Vacation $vacation): bool
    {
        return $user->can('view vacations')
            && $this->inOrg($user, $vacation);
    }

    public function create(User $user): bool
    {
        return $user->can('create vacations');
    }

    public function update(User $user, Vacation $vacation): bool
    {
        return $user->can('edit vacations')
            && $this->inOrg($user, $vacation);
    }

    public function delete(User $user, Vacation $vacation): bool
    {
        return $user->can('delete vacations')
            && $this->inOrg($user, $vacation);
    }

    public function approve(User $user, Vacation $vacation): bool
    {
        return $user->can('approve vacations')
            && $this->inOrg($user, $vacation)
            && $user->id !== $vacation->user_id;
    }

    public function reject(User $user, Vacation $vacation): bool
    {
        return $user->can('reject vacations')
            && $this->inOrg($user, $vacation)
            && $user->id !== $vacation->user_id;
    }

    private function inOrg(User $user, Vacation $vacation): bool
    {
        return $vacation->organization_id === $user->activeOrganizationId();
    }
}
