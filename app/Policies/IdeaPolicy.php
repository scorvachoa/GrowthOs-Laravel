<?php

namespace App\Policies;

use App\Models\Idea;
use App\Models\User;

class IdeaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view ideas');
    }

    public function view(User $user, Idea $idea): bool
    {
        return $user->can('view ideas')
            && $this->inOrg($user, $idea);
    }

    public function create(User $user): bool
    {
        return $user->can('create ideas');
    }

    public function update(User $user, Idea $idea): bool
    {
        return $user->can('edit ideas')
            && $this->inOrg($user, $idea);
    }

    public function delete(User $user, Idea $idea): bool
    {
        return $user->can('delete ideas')
            && $this->inOrg($user, $idea);
    }

    private function inOrg(User $user, Idea $idea): bool
    {
        return $idea->organization_id === $user->activeOrganizationId();
    }
}
