<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\VideoTask;
use App\Services\YouTubeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class YoutubeController extends Controller
{
    public function __construct(
        protected YouTubeService $youtube
    ) {}

    public function index()
    {
        $maxVideos = Auth::user()->merged_settings['youtube_max_recent_videos'] ?? 10;

        $channels = Channel::query()->orderBy('name')->get()->map(function ($c) use ($maxVideos) {
            $data = [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'youtube_channel_id' => $c->youtube_channel_id,
                'channel_url' => $c->channel_url,
                'videos' => [],
            ];

            if ($c->youtube_channel_id) {
                $stats = $this->youtube->channelStats($c->youtube_channel_id);
                if ($stats) {
                    $data['live_title'] = $stats['title'];
                    $data['thumbnail'] = $stats['thumbnail'];
                    $data['custom_url'] = $stats['custom_url'];
                    $data['subscriber_count'] = $stats['subscriber_count'];
                    $data['live_video_count'] = $stats['video_count'];
                    $data['view_count'] = $stats['view_count'];
                    $data['country'] = $stats['country'];
                }

                $data['videos'] = $this->youtube->recentVideos($c->youtube_channel_id, $maxVideos);
            }

            return $data;
        });

        $statusStats = VideoTask::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentPublished = VideoTask::query()
            ->where('status', 'published')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'task_date' => $t->task_date?->format('Y-m-d'),
                'youtube_url' => $t->youtube_url,
                'updated_at' => $t->updated_at->diffForHumans(),
            ]);

        $publishedThisMonth = VideoTask::query()
            ->where('status', 'published')
            ->whereMonth('task_date', Carbon::now()->month)
            ->whereYear('task_date', Carbon::now()->year)
            ->count();

        return Inertia::render('Youtube/Index', [
            'channels' => $channels,
            'stats' => [
                'total_channels' => $channels->count(),
                'total_videos' => VideoTask::query()->count(),
                'published_total' => $statusStats['published'] ?? 0,
                'published_this_month' => $publishedThisMonth,
                'pending_count' => $statusStats['pending'] ?? 0,
                'statuses' => $statusStats,
                'api_connected' => $this->youtube->available(),
            ],
            'recent_published' => $recentPublished,
        ]);
    }
}
