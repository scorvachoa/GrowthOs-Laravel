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
            && $this->inOrg($user, $idea)
            && $this->isOwnerOrAdmin($user, $idea);
    }

    public function create(User $user): bool
    {
        return $user->can('create ideas');
    }

    public function update(User $user, Idea $idea): bool
    {
        return $user->can('edit ideas')
            && $this->inOrg($user, $idea)
            && $this->isOwnerOrAdmin($user, $idea);
    }

    public function delete(User $user, Idea $idea): bool
    {
        return $user->can('delete ideas')
            && $this->inOrg($user, $idea)
            && $this->isOwnerOrAdmin($user, $idea);
    }

    private function inOrg(User $user, Idea $idea): bool
    {
        return $idea->organization_id === $user->activeOrganizationId();
    }

    private function isOwnerOrAdmin(User $user, Idea $idea): bool
    {
        return $user->hasRole(['Super Admin', 'Admin'])
            || $idea->created_by === $user->id;
    }
}
