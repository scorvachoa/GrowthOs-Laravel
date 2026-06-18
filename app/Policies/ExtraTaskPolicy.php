<?php

namespace App\Policies;

use App\Models\ExtraTask;
use App\Models\User;

class ExtraTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view planning');
    }

    public function view(User $user, ExtraTask $extraTask): bool
    {
        return $user->can('view planning')
            && $this->inOrg($user, $extraTask);
    }

    public function create(User $user): bool
    {
        return $user->can('create planning');
    }

    public function update(User $user, ExtraTask $extraTask): bool
    {
        return $user->can('edit planning')
            && $this->inOrg($user, $extraTask);
    }

    public function delete(User $user, ExtraTask $extraTask): bool
    {
        return $user->can('delete planning')
            && $this->inOrg($user, $extraTask);
    }

    private function inOrg(User $user, ExtraTask $extraTask): bool
    {
        return $extraTask->organization_id === $user->activeOrganizationId();
    }
}
