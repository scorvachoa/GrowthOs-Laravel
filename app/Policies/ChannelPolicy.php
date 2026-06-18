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
        return $user->can('view empresa')
            && $this->inOrg($user, $channel);
    }

    public function create(User $user): bool
    {
        return $user->can('create empresa');
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->can('edit empresa')
            && $this->inOrg($user, $channel);
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->can('delete empresa')
            && $this->inOrg($user, $channel);
    }

    private function inOrg(User $user, Channel $channel): bool
    {
        return $channel->organization_id === $user->activeOrganizationId();
    }
}
