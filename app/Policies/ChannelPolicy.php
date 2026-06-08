<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view empresa');
    }

    public function view(User $user, Channel $channel): bool
    {
        return $user->can('view empresa');
    }

    public function create(User $user): bool
    {
        return $user->can('create empresa');
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->can('edit empresa');
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->can('delete empresa');
    }
}
