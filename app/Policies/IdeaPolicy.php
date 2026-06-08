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
        return $user->can('view ideas');
    }

    public function create(User $user): bool
    {
        return $user->can('create ideas');
    }

    public function update(User $user, Idea $idea): bool
    {
        return $user->can('edit ideas');
    }

    public function delete(User $user, Idea $idea): bool
    {
        return $user->can('delete ideas');
    }
}
