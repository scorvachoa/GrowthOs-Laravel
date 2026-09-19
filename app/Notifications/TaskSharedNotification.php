<?php

namespace App\Notifications;

use App\Models\ExtraTask;
use App\Models\TaskShare;
use App\Models\User;
use App\Models\VideoTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskSharedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $sharedBy,
        public VideoTask|ExtraTask $task,
        public string $taskType,
        public ?TaskShare $taskShare = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $typeName = $this->taskType === 'video_task' ? 'tarea de video' : 'tarea extra';

        return [
            'shared_by_user_id' => $this->sharedBy->id,
            'shared_by_user_name' => $this->sharedBy->name,
            'task_type' => $this->taskType,
            'task_id' => $this->task->id,
            'task_title' => $this->task->title ?? 'Sin título',
            'task_date' => $this->task->task_date?->format('Y-m-d'),
            'task_share_id' => $this->taskShare?->id,
            'message' => "{$this->sharedBy->name} compartió una {$typeName} contigo: {$this->task->title}",
            'action_url' => route('planning.index'),
        ];
    }
}
