<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\VideoTask;
use App\Services\YouTube\CommentsService;
use App\Services\YouTube\YouTubeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class YoutubeController extends Controller
{
    public function __construct(
        protected YouTubeService $youtube,
        protected CommentsService $comments
    ) {}

    public function index()
    {
        $userId = Auth::id();
        $cacheKey = "youtube_index_{$userId}";

        // Cache the entire page data for 5 minutes to avoid repeated API calls
        $data = Cache::remember($cacheKey, 300, function () {
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

            return ['channels' => $channels];
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

        $monthStart = Carbon::now()->startOfMonth();
        $publishedThisMonth = VideoTask::query()
            ->where('status', 'published')
            ->where('task_date', '>=', $monthStart)
            ->where('task_date', '<', $monthStart->copy()->addMonth())
            ->count();

        return Inertia::render('Youtube/Index', [
            'channels' => $data['channels'],
            'stats' => [
                'total_channels' => $data['channels']->count(),
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

    /**
     * Multi-channel inbox with the latest comments from YouTube.
     */
    public function comments()
    {
        $userId = Auth::id();

        $data = Cache::remember("youtube_comments_{$userId}", 120, function () {
            $channels = Channel::query()
                ->whereNotNull('youtube_channel_id')
                ->where('youtube_channel_id', '!=', '')
                ->orderBy('name')
                ->get();

            $since = now()->subDays(7)->timestamp;

            $avatars = $this->comments->channelAvatars(
                $channels->pluck('youtube_channel_id')->all()
            );

            $threads = $channels
                ->flatMap(fn ($channel) => $this->comments->channelThreads($channel, 20))
                ->filter(function ($thread) use ($since) {
                    $ts = strtotime($thread['published_at'] ?? '');

                    return $ts !== false && $ts >= $since;
                })
                ->sortByDesc('published_at')
                ->values();

            return [
                'channels' => $channels->map(fn ($channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'color' => $channel->color,
                    'thumbnail' => $avatars[$channel->youtube_channel_id] ?? null,
                ])->values(),
                'threads' => $threads,
            ];
        });

        return Inertia::render('Youtube/Comments', [
            'channels' => $data['channels'],
            'threads' => $data['threads'],
            'api_connected' => $this->comments->available(),
        ]);
    }

    /**
     * Bust cache when channels are updated.
     */
    public static function bustCache(): void
    {
        $userId = Auth::id();
        if ($userId) {
            Cache::forget("youtube_index_{$userId}");
        }
    }
}
