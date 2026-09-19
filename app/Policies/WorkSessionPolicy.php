<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSession;

class WorkSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view planning');
    }

    public function view(User $user, WorkSession $workSession): bool
    {
        return $user->can('view planning')
            && $this->inOrg($user, $workSession);
    }

    public function create(User $user): bool
    {
        return $user->can('edit planning');
    }

    public function update(User $user, WorkSession $workSession): bool
    {
        return $user->can('edit planning')
            && $this->inOrg($user, $workSession);
    }

    public function delete(User $user, WorkSession $workSession): bool
    {
        return $user->can('delete planning')
            && $this->inOrg($user, $workSession);
    }

    private function inOrg(User $user, WorkSession $workSession): bool
    {
        return $workSession->organization_id === $user->activeOrganizationId();
    }
}
