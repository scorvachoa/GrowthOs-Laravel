<?php

namespace App\Services;

use App\Models\User;
use App\Models\VideoTask;
use App\Notifications\TaskSharedNotification;

class TaskShareService
{
    /**
     * Replace the shares owned by the current user on a task and notify the
     * recipients that did not already have access to it.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, string>  $roles
     */
    public function sync(object $task, array $userIds, array $roles = [], bool $existingScopedToAuthor = false): void
    {
        $currentUserId = auth()->id();

        $existingQuery = $task->shares();
        if ($existingScopedToAuthor) {
            $existingQuery->where('shared_by_user_id', $currentUserId);
        }
        $existing = $existingQuery->pluck('shared_with_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $task->shares()->where('shared_by_user_id', $currentUserId)->delete();

        $taskType = $task instanceof VideoTask ? 'video_task' : 'extra_task';

        foreach ($userIds as $idx => $userId) {
            if ((int) $userId === $currentUserId) {
                continue;
            }

            $share = $task->shares()->create([
                'shared_with_user_id' => $userId,
                'shared_by_user_id' => $currentUserId,
                'role' => $roles[$idx] ?? 'editor',
            ]);

            if (! in_array((int) $userId, $existing, true)) {
                User::find($userId)?->notify(new TaskSharedNotification(
                    auth()->user(), $task, $taskType, $share
                ));
            }
        }
    }
}
