<?php

namespace App\Policies;

use App\Models\GeneratedVideo;
use App\Models\User;

class GeneratedVideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view ai history');
    }

    public function view(User $user, GeneratedVideo $generatedVideo): bool
    {
        return $user->can('view ai history')
            && $this->inOrg($user, $generatedVideo);
    }

    public function create(User $user): bool
    {
        return $user->can('generate ai');
    }

    public function delete(User $user, GeneratedVideo $generatedVideo): bool
    {
        return $user->can('view ai history')
            && $this->inOrg($user, $generatedVideo)
            && $this->ownedBy($user, $generatedVideo);
    }

    public function download(User $user, GeneratedVideo $generatedVideo): bool
    {
        return $user->can('download ai')
            && $this->inOrg($user, $generatedVideo);
    }

    private function inOrg(User $user, GeneratedVideo $generatedVideo): bool
    {
        return $generatedVideo->organization_id === $user->activeOrganizationId();
    }

    private function ownedBy(User $user, GeneratedVideo $generatedVideo): bool
    {
        return $generatedVideo->user_id === $user->id;
    }
}
