<?php

namespace App\Services\YouTube;

use App\Enums\VideoTaskStatus;
use App\Models\VideoTask;
use App\Services\PlanningCalendarService;

class AutoPublishService
{
    public function __construct(protected YouTubeService $youtube) {}

    public function publishLiveScheduled(): int
    {
        if (! $this->youtube->available()) {
            return 0;
        }

        $tasks = VideoTask::query()
            ->withoutGlobalScope('organization')
            ->where('status', VideoTaskStatus::Scheduled->value)
            ->whereNotNull('youtube_url')
            ->get();

        $taskIdsByVideo = [];
        foreach ($tasks as $task) {
            $videoId = YouTubeService::extractVideoId($task->youtube_url);
            if ($videoId) {
                $taskIdsByVideo[$videoId][] = $task;
            }
        }

        if ($taskIdsByVideo === []) {
            return 0;
        }

        $liveVideoIds = $this->youtube->liveVideoIds(array_keys($taskIdsByVideo));
        if ($liveVideoIds === []) {
            return 0;
        }

        $published = 0;
        foreach ($liveVideoIds as $videoId) {
            foreach ($taskIdsByVideo[$videoId] ?? [] as $task) {
                $task->update(['status' => VideoTaskStatus::Published->value]);
                $task->activities()->latest()->first()?->update(['description' => 'Publicado automáticamente']);
                $published++;
            }
        }

        if ($published > 0) {
            PlanningCalendarService::bustCache();
        }

        return $published;
    }
}
