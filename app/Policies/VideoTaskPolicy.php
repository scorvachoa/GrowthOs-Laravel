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
        return $user->can('view planning');
    }

    public function create(User $user): bool
    {
        return $user->can('create planning');
    }

    public function update(User $user, VideoTask $videoTask): bool
    {
        return $user->can('edit planning');
    }

    public function delete(User $user, VideoTask $videoTask): bool
    {
        return $user->can('delete planning');
    }
}
