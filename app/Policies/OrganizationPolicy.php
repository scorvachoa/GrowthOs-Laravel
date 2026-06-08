<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view empresa');
    }

    public function view(User $user, Organization $organization): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return $user->organization_id === $organization->id && $user->can('view empresa');
    }

    public function create(User $user): bool
    {
        return $user->can('create empresa');
    }

    public function update(User $user, Organization $organization): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return $user->organization_id === $organization->id && $user->can('edit empresa');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can('delete empresa');
    }
}
