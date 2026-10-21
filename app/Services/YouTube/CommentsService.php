<?php

namespace App\Services\YouTube;

use App\Models\Channel;
use Google\Client;
use Google\Service\YouTube;
use Illuminate\Support\Facades\Log;

class CommentsService
{
    protected ?Client $client = null;

    protected ?YouTube $youtube = null;

    public function __construct()
    {
        $apiKey = config('services.youtube.api_key');
        if (! $apiKey) {
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

    /**
     * Latest comment threads of every video of the given channel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function channelThreads(Channel $channel, int $limit = 20): array
    {
        if (! $this->available() || ! $channel->youtube_channel_id) {
            return [];
        }

        try {
            $response = $this->youtube->commentThreads->listCommentThreads('snippet,replies', [
                'allThreadsRelatedToChannelId' => $channel->youtube_channel_id,
                'order' => 'time',
                'maxResults' => max(1, min($limit, 100)),
                'textFormat' => 'plainText',
            ]);

            $threads = $response->getItems() ?: [];
            if ($threads === []) {
                return [];
            }

            $videoIds = array_values(array_unique(array_map(
                fn ($thread) => $thread->getSnippet()->getTopLevelComment()->getSnippet()->getVideoId(),
                $threads
            )));
            $videoTitles = $this->videoTitles($videoIds);

            return array_map(
                fn ($thread) => $this->mapThread($thread, $channel, $videoTitles),
                $threads
            );
        } catch (\Exception $e) {
            Log::warning("YouTube API error fetching comments for channel {$channel->id}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * @param  array<int, string>  $channelIds
     * @return array<string, string>
     */
    public function channelAvatars(array $channelIds): array
    {
        $channelIds = array_values(array_filter($channelIds));
        if (! $this->available() || $channelIds === []) {
            return [];
        }

        $avatars = [];

        try {
            foreach (array_chunk($channelIds, 50) as $chunk) {
                $response = $this->youtube->channels->listChannels('snippet', [
                    'id' => implode(',', $chunk),
                ]);

                foreach ($response->getItems() as $channel) {
                    $thumbnails = $channel->getSnippet()->getThumbnails();
                    $default = $thumbnails?->getDefault();

                    if ($default?->getUrl()) {
                        $avatars[$channel->getId()] = $default->getUrl();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("YouTube API error fetching channel avatars: {$e->getMessage()}");
        }

        return $avatars;
    }

    /**
     * @param  array<int, string>  $videoIds
     * @return array<string, string>
     */
    protected function videoTitles(array $videoIds): array
    {
        $videoIds = array_values(array_filter($videoIds));
        if ($videoIds === []) {
            return [];
        }

        try {
            $response = $this->youtube->videos->listVideos('snippet', [
                'id' => implode(',', $videoIds),
            ]);

            $titles = [];
            foreach ($response->getItems() as $video) {
                $titles[$video->getId()] = $video->getSnippet()->getTitle();
            }

            return $titles;
        } catch (\Exception $e) {
            Log::warning("YouTube API error fetching video titles: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * @param  array<string, string>  $videoTitles
     * @return array<string, mixed>
     */
    protected function mapThread($thread, Channel $channel, array $videoTitles): array
    {
        $snippet = $thread->getSnippet();
        $topLevel = $snippet->getTopLevelComment();
        $comment = $topLevel->getSnippet();
        $videoId = $comment->getVideoId();

        $replies = [];
        foreach ($thread->getReplies()?->getComments() ?: [] as $reply) {
            $replySnippet = $reply->getSnippet();
            $replies[] = [
                'id' => $reply->getId(),
                'author' => $replySnippet->getAuthorDisplayName(),
                'avatar' => $replySnippet->getAuthorProfileImageUrl(),
                'text' => $replySnippet->getTextOriginal(),
                'published_at' => $replySnippet->getPublishedAt(),
            ];
        }

        return [
            'id' => $topLevel->getId(),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'channel_color' => $channel->color,
            'video_id' => $videoId,
            'video_title' => $videoTitles[$videoId] ?? null,
            'video_url' => "https://www.youtube.com/watch?v={$videoId}",
            'author' => $comment->getAuthorDisplayName(),
            'avatar' => $comment->getAuthorProfileImageUrl(),
            'text' => $comment->getTextOriginal(),
            'published_at' => $comment->getPublishedAt(),
            'likes' => (int) $comment->getLikeCount(),
            'total_replies' => (int) $snippet->getTotalReplyCount(),
            'replies' => $replies,
        ];
    }
}
