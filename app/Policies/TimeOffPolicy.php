<?php

namespace App\Policies;

use App\Models\TimeOff;
use App\Models\User;

class TimeOffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view time off');
    }

    public function view(User $user, TimeOff $timeOff): bool
    {
        return $user->can('view time off')
            && $this->inOrg($user, $timeOff);
    }

    public function create(User $user): bool
    {
        return $user->can('create time off');
    }

    public function update(User $user, TimeOff $timeOff): bool
    {
        return $user->can('edit time off')
            && $this->inOrg($user, $timeOff);
    }

    public function delete(User $user, TimeOff $timeOff): bool
    {
        return $user->can('delete time off')
            && $this->inOrg($user, $timeOff);
    }

    public function approve(User $user, TimeOff $timeOff): bool
    {
        return $user->can('approve time off')
            && $this->inOrg($user, $timeOff)
            && $user->id !== $timeOff->user_id;
    }

    public function reject(User $user, TimeOff $timeOff): bool
    {
        return $user->can('reject time off')
            && $this->inOrg($user, $timeOff)
            && $user->id !== $timeOff->user_id;
    }

    private function inOrg(User $user, TimeOff $timeOff): bool
    {
        return $timeOff->organization_id === $user->activeOrganizationId();
    }
}
