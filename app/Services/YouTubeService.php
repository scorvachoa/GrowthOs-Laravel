<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected ?Client $client = null;

    protected ?YouTube $youtube = null;

    public function __construct()
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            return;
        }
        $this->client = new Client;
        $this->client->setDeveloperKey($apiKey);
        $this->youtube = new YouTube($this->client);
    }

    public function available(): bool
    {
        return $this->youtube !== null;
    }

    public function channelStats(string $channelId): ?array
    {
        if (!$this->available()) {
            return null;
        }

        $cacheKey = "youtube_channel_{$channelId}";

        return Cache::remember($cacheKey, 3600, function () use ($channelId) {
            try {
                $response = $this->youtube->channels->listChannels('snippet,statistics', [
                    'id' => $channelId,
                ]);

                if (empty($response->getItems())) {
                    return null;
                }

                $channel = $response->getItems()[0];
                $stats = $channel->getStatistics();

                return [
                    'title' => $channel->getSnippet()->getTitle(),
                    'description' => $channel->getSnippet()->getDescription(),
                    'thumbnail' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                    'custom_url' => $channel->getSnippet()->getCustomUrl(),
                    'subscriber_count' => (int) $stats->getSubscriberCount(),
                    'video_count' => (int) $stats->getVideoCount(),
                    'view_count' => (int) $stats->getViewCount(),
                    'country' => $channel->getSnippet()->getCountry(),
                ];
            } catch (\Exception $e) {
                Log::warning("YouTube API error for channel {$channelId}: {$e->getMessage()}");
                return null;
            }
        });
    }

    public function uploadsPlaylistId(string $channelId): ?string
    {
        if (!$this->available()) return null;

        $cacheKey = "youtube_uploads_playlist_{$channelId}";

        return Cache::remember($cacheKey, 86400, function () use ($channelId) {
            try {
                $response = $this->youtube->channels->listChannels('contentDetails', [
                    'id' => $channelId,
                ]);

                if (empty($response->getItems())) {
                    return null;
                }

                return $response->getItems()[0]
                    ->getContentDetails()
                    ->getRelatedPlaylists()
                    ->getUploads();
            } catch (\Exception $e) {
                Log::warning("YouTube API error getting uploads playlist for {$channelId}: {$e->getMessage()}");
                return null;
            }
        });
    }

    public function recentVideos(string $channelId, int $max = 10): array
    {
        if (!$this->available()) {
            return [];
        }

        $cacheKey = "youtube_videos_{$channelId}_{$max}";

        return Cache::remember($cacheKey, 1800, function () use ($channelId, $max) {
            try {
                $playlistId = $this->uploadsPlaylistId($channelId);

                if (!$playlistId) {
                    return [];
                }

                $playlistItems = $this->youtube->playlistItems->listPlaylistItems('snippet', [
                    'playlistId' => $playlistId,
                    'maxResults' => $max,
                ]);

                $videoIds = [];
                $items = $playlistItems->getItems();

                foreach ($items as $item) {
                    $videoIds[] = $item->getSnippet()->getResourceId()->getVideoId();
                }

                $statsMap = [];
                if (!empty($videoIds)) {
                    $videoResponse = $this->youtube->videos->listVideos('statistics', [
                        'id' => implode(',', $videoIds),
                    ]);
                    foreach ($videoResponse->getItems() as $video) {
                        $stats = $video->getStatistics();
                        $statsMap[$video->getId()] = [
                            'view_count' => (int) $stats->getViewCount(),
                            'comment_count' => (int) $stats->getCommentCount(),
                            'like_count' => (int) $stats->getLikeCount(),
                        ];
                    }
                }

                $videos = [];
                foreach ($items as $item) {
                    $snippet = $item->getSnippet();
                    $videoId = $snippet->getResourceId()->getVideoId();
                    $stats = $statsMap[$videoId] ?? null;
                    $videos[] = [
                        'id' => $videoId,
                        'title' => $snippet->getTitle(),
                        'description' => $snippet->getDescription(),
                        'thumbnail' => $snippet->getThumbnails()->getDefault()->getUrl(),
                        'medium_thumbnail' => $snippet->getThumbnails()->getMedium()->getUrl(),
                        'high_thumbnail' => $snippet->getThumbnails()->getHigh()->getUrl(),
                        'published_at' => $snippet->getPublishedAt(),
                        'url' => "https://youtube.com/watch?v={$videoId}",
                        'view_count' => $stats['view_count'] ?? 0,
                        'comment_count' => $stats['comment_count'] ?? 0,
                        'like_count' => $stats['like_count'] ?? 0,
                    ];
                }

                return $videos;
            } catch (\Exception $e) {
                Log::warning("YouTube API error fetching videos for {$channelId}: {$e->getMessage()}");
                return [];
            }
        });
    }
}
