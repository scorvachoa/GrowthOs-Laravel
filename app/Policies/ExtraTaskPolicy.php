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
        return $user->can('view planning');
    }

    public function create(User $user): bool
    {
        return $user->can('create planning');
    }

    public function update(User $user, ExtraTask $extraTask): bool
    {
        return $user->can('edit planning');
    }

    public function delete(User $user, ExtraTask $extraTask): bool
    {
        return $user->can('delete planning');
    }
}
