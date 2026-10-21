<?php

namespace App\Services\Facebook;

use App\Models\SocialConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PageService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly AuthService $auth,
    ) {}

    /**
     * Publish a post to the page now (needs pages_manage_posts). With a photo
     * URL the Graph API fetches the image itself (photo post); otherwise the
     * message goes out as a link/status post. Clears the feed cache.
     *
     * @throws FacebookGraphException
     */
    public function publish(int $orgId, string $message, ?string $link = null, ?string $photo = null): string
    {
        $connection = $this->connectionOrFail($orgId);

        if ($photo) {
            $raw = $this->graph->post($connection->page_id.'/photos', $connection->access_token, [
                'url' => $photo,
                'message' => $message,
                'published' => 'true',
            ]);
        } else {
            $params = ['message' => $message];

            if ($link) {
                $params['link'] = $link;
            }

            $raw = $this->graph->post($connection->page_id.'/feed', $connection->access_token, $params);
        }

        Cache::forget("facebook_page_feed:{$orgId}");

        return (string) ($raw['id'] ?? '');
    }

    /**
     * Edit the text (and optionally replace the link) of an existing page
     * post (needs pages_manage_posts). Clears the feed cache.
     *
     * @throws FacebookGraphException
     */
    public function updatePost(int $orgId, string $postId, string $message, ?string $link = null): void
    {
        $connection = $this->connectionOrFail($orgId);

        $params = ['message' => $message];

        if ($link) {
            $params['link'] = $link;
        }

        $this->graph->post($postId, $connection->access_token, $params);

        Cache::forget("facebook_page_feed:{$orgId}");
    }

    /**
     * Delete a post permanently (needs pages_manage_posts). Clears the feed
     * cache.
     *
     * @throws FacebookGraphException
     */
    public function deletePost(int $orgId, string $postId): void
    {
        $connection = $this->connectionOrFail($orgId);

        $this->graph->delete($postId, $connection->access_token);

        Cache::forget("facebook_page_feed:{$orgId}");
    }

    private function connectionOrFail(int $orgId): SocialConnection
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        return $connection;
    }

    /**
     * Latest page posts (5 min cache, 60s negative cache on failure).
     * Returns null when the org has no connection, or
     * ['posts' => [], 'error' => true] when the Graph API fails.
     */
    public function feed(int $orgId, int $limit = 15): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "facebook_page_feed:{$orgId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $raw = $this->graph->get($connection->page_id.'/feed', $connection->access_token, [
                'fields' => 'id,message,story,created_time,permalink_url,full_picture,attachments{media{source},media_type},likes.summary(true).limit(0),comments.summary(true).limit(0)',
                'limit' => $limit,
            ]);

            $result = ['posts' => $this->mapPosts($raw['data'] ?? []), 'error' => false];
            Cache::put($cacheKey, $result, 300);
        } catch (Throwable $e) {
            Log::warning('Facebook feed fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['posts' => [], 'error' => true];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Daily page insights for the last N days plus the previous N-day window
     * for period comparison (10 min cache per range, 60s negative cache).
     * available=false when Graph rejects the request (bad token, deprecated
     * metric, etc.).
     *
     * Only metrics still alive after Meta's June 2026 Page Insights
     * deprecation: page_impressions, page_engaged_users, page_fan_adds and
     * most legacy metrics now return "(#100) The value must be a valid
     * insights metric".
     */
    public function insights(int $orgId, int $days = 28): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "facebook_page_insights:{$orgId}:{$days}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $emptyTotals = ['views' => 0, 'engagements' => 0, 'video_views' => 0];

        try {
            $raw = $this->graph->get($connection->page_id.'/insights', $connection->access_token, [
                'metric' => 'page_views_total,page_post_engagements,page_video_views',
                'period' => 'day',
                'since' => now()->subDays($days * 2)->startOfDay()->getTimestamp(),
                'until' => now()->getTimestamp(),
            ]);

            $cutoff = now()->subDays($days)->toDateString();
            $current = [];
            $previous = [];

            foreach (($raw['data'] ?? []) as $metric) {
                foreach ($metric['values'] ?? [] as $value) {
                    $date = substr($value['end_time'] ?? '', 0, 10);

                    if (! $date) {
                        continue;
                    }

                    $node = [
                        'name' => $metric['name'] ?? null,
                        'period' => $metric['period'] ?? null,
                        'values' => [$value],
                    ];

                    if ($date >= $cutoff) {
                        $current[] = $node;
                    } else {
                        $previous[] = $node;
                    }
                }
            }

            $currentResult = $this->mapInsights($current);
            $previousResult = $this->mapInsights($previous);

            $result = array_merge(
                ['available' => true, 'error' => null],
                $currentResult,
                ['previous_totals' => $previousResult['totals'], 'previous_days' => $previousResult['days']]
            );
            Cache::put($cacheKey, $result, 600);
        } catch (Throwable $e) {
            Log::warning('Facebook insights fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = [
                'available' => false,
                'error' => $e->getMessage(),
                'days' => [],
                'totals' => $emptyTotals,
                'previous_totals' => $emptyTotals,
                'previous_days' => [],
            ];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    private function mapInsights(array $items): array
    {
        $metricMap = [
            'page_views_total' => 'views',
            'page_post_engagements' => 'engagements',
            'page_video_views' => 'video_views',
        ];

        $byDate = [];

        foreach ($items as $metric) {
            $field = $metricMap[$metric['name'] ?? ''] ?? null;

            if (! $field) {
                continue;
            }

            foreach ($metric['values'] ?? [] as $value) {
                $date = substr($value['end_time'] ?? '', 0, 10);

                if (! $date) {
                    continue;
                }

                $byDate[$date][$field] = ($byDate[$date][$field] ?? 0) + (int) ($value['value'] ?? 0);
            }
        }

        ksort($byDate);

        $days = [];
        $totals = ['views' => 0, 'engagements' => 0, 'video_views' => 0];

        foreach ($byDate as $date => $row) {
            $entry = [
                'date' => $date,
                'views' => $row['views'] ?? 0,
                'engagements' => $row['engagements'] ?? 0,
                'video_views' => $row['video_views'] ?? 0,
            ];
            $days[] = $entry;

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $entry[$key];
            }
        }

        return ['days' => $days, 'totals' => $totals];
    }

    private function mapPosts(array $items): array
    {
        return array_map(function (array $post) {
            $attachments = $post['attachments']['data'] ?? [];
            $video = collect($attachments)->firstWhere('media_type', 'video');

            return [
                'id' => $post['id'] ?? null,
                'message' => $post['message'] ?? $post['story'] ?? '',
                'created_time' => $post['created_time'] ?? null,
                'permalink_url' => $post['permalink_url'] ?? null,
                'full_picture' => $post['full_picture'] ?? null,
                'likes' => (int) ($post['likes']['summary']['count'] ?? 0),
                'comments' => (int) ($post['comments']['summary']['count'] ?? 0),
                'is_video' => ! is_null($video),
                'video_url' => $video['media']['source'] ?? null,
            ];
        }, array_values($items));
    }
}
