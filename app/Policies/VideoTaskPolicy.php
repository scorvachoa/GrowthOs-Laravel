<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoTask;

class VideoTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view planning');
    }

    public function view(User $user, VideoTask $videoTask): bool
    {
        return $user->can('view planning')
            && $this->inOrg($user, $videoTask);
    }

    public function create(User $user): bool
    {
        return $user->can('create planning');
    }

    public function update(User $user, VideoTask $videoTask): bool
    {
        return $user->can('edit planning')
            && $this->inOrg($user, $videoTask);
    }

    public function delete(User $user, VideoTask $videoTask): bool
    {
        return $user->can('delete planning')
            && $this->inOrg($user, $videoTask);
    }

    private function inOrg(User $user, VideoTask $videoTask): bool
    {
        return $videoTask->organization_id === $user->activeOrganizationId();
    }
}
