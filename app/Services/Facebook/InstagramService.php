<?php

namespace App\Services\Facebook;

use App\Models\SocialConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Instagram lives in this folder because it rides on the exact same Meta
 * Graph API, page token and SocialConnection as the Facebook integration
 * (the account is resolved through the connected page's
 * instagram_business_account field).
 */
class InstagramService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly AuthService $auth,
    ) {}

    /**
     * Instagram Business/Creator account linked to the connected page
     * (10 min cache on success, 60s when not linked or on failure — so a
     * newly linked account shows up quickly). Returns null when the org
     * has no Facebook connection, otherwise ['linked' => bool,
     * 'error' => ?string] plus the account fields when linked.
     */
    public function account(int $orgId): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "instagram_account:{$orgId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $raw = $this->graph->get($connection->page_id, $connection->access_token, [
                'fields' => 'instagram_business_account{id,username,name,profile_picture_url,followers_count,follows_count,media_count}',
            ]);

            $ig = $raw['instagram_business_account'] ?? null;

            if (! is_array($ig) || empty($ig['id'])) {
                $result = ['linked' => false, 'error' => null];
                Cache::put($cacheKey, $result, 120);
            } else {
                $result = [
                    'linked' => true,
                    'error' => null,
                    'stale' => false,
                    'id' => (string) $ig['id'],
                    'username' => $ig['username'] ?? null,
                    'name' => $ig['name'] ?? null,
                    'picture' => $ig['profile_picture_url'] ?? null,
                    'followers' => $ig['followers_count'] ?? null,
                    'follows' => $ig['follows_count'] ?? null,
                    'media_count' => $ig['media_count'] ?? null,
                ];
                Cache::put($cacheKey, $result, 600);
            }
        } catch (Throwable $e) {
            Log::warning('Instagram account fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['linked' => false, 'stale' => true, 'error' => $e->getMessage()];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Latest media of the linked account (5 min cache, 60s negative cache).
     * Returns null when the org has no page connection,
     * ['media' => [], 'error' => false] when no Instagram is linked, or
     * ['media' => [], 'error' => true] when the Graph API fails.
     */
    public function feed(int $orgId, int $limit = 15): ?array
    {
        $account = $this->account($orgId);

        if ($account === null) {
            return null;
        }

        if (! ($account['linked'] ?? false)) {
            return ['media' => [], 'error' => false];
        }

        $cacheKey = "instagram_feed:{$orgId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $connection = $this->auth->connection($orgId);

        try {
            $raw = $this->graph->get($account['id'].'/media', $connection->access_token, [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count,username',
                'limit' => $limit,
            ]);

            $result = ['media' => $this->mapMedia($raw['data'] ?? []), 'error' => false];
            Cache::put($cacheKey, $result, 300);
        } catch (Throwable $e) {
            Log::warning('Instagram feed fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['media' => [], 'error' => true];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Daily account insights for the last N days plus the previous N-day
     * window for period comparison (10 min cache per range, 60s negative
     * cache). available=false when every metric set is rejected by Graph.
     *
     * Tries progressively smaller metric sets because Instagram rejects the
     * whole request when a single metric is unavailable for the account.
     */
    public function insights(int $orgId, int $days = 28): ?array
    {
        $account = $this->account($orgId);

        if ($account === null || ! ($account['linked'] ?? false)) {
            return null;
        }

        $cacheKey = "instagram_insights:{$orgId}:{$days}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $emptyTotals = ['reach' => 0, 'impressions' => 0, 'followers' => 0, 'profile_views' => 0];

        try {
            $connection = $this->auth->connection($orgId);

            // `impressions` was dropped: Meta rejects it for account
            // insights ("(#100) metric[i] must be one of the following
            // values: reach, follower_count, website_clicks, ...").
            $metricSets = [
                'reach,profile_views,follower_count',
                'reach,follower_count',
                'reach',
            ];

            // Instagram rejects a single request whose since/until span
            // exceeds 30 days (Facebook allows longer windows), so the
            // 2N-day range is fetched in <=30 day chunks, newest first
            // (the first chunk probes the metric chain; later chunks that
            // fail — e.g. data retention limits — are skipped).
            $windows = $this->insightWindows(now()->subDays($days * 2), now());
            $resolvedMetric = null;
            $lastError = null;
            $byDate = [];

            foreach (array_reverse($windows) as [$since, $until]) {
                $raw = null;
                $chain = $this->preferredFirst($metricSets, $resolvedMetric);

                foreach ($chain as $metric) {
                    try {
                        $raw = $this->graph->get($account['id'].'/insights', $connection->access_token, [
                            'metric' => $metric,
                            'period' => 'day',
                            'since' => $since,
                            'until' => $until,
                        ]);
                        $resolvedMetric = $metric;
                        break;
                    } catch (Throwable $e) {
                        $lastError = $e;
                    }
                }

                if ($raw === null) {
                    if ($resolvedMetric === null) {
                        throw $lastError ?? new FacebookGraphException('Sin datos de Instagram');
                    }

                    Log::warning('Instagram insights chunk skipped', [
                        'organization_id' => $orgId,
                        'since' => $since,
                        'until' => $until,
                        'error' => $lastError?->getMessage(),
                    ]);

                    continue;
                }

                foreach (($raw['data'] ?? []) as $metricNode) {
                    $name = $metricNode['name'] ?? null;

                    if (! $name) {
                        continue;
                    }

                    foreach ($metricNode['values'] ?? [] as $value) {
                        $date = substr($value['end_time'] ?? '', 0, 10);

                        if ($date) {
                            $byDate[$name][$date] = (int) ($value['value'] ?? 0);
                        }
                    }
                }
            }

            $cutoff = now()->subDays($days)->toDateString();
            $current = [];
            $previous = [];

            foreach ($byDate as $name => $values) {
                foreach ($values as $date => $value) {
                    $node = [
                        'name' => $name,
                        'period' => 'day',
                        'values' => [['value' => $value, 'end_time' => $date]],
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
            Log::warning('Instagram insights fetch failed', [
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

    /**
     * Publish media now: a container is created (image_url or video_url +
     * caption, media_type=REELS for reels) and then published. Video
     * containers are polled until Instagram finishes processing them
     * (up to ~45s). Needs instagram_content_publish. Clears the local
     * caches so the new media shows up on the next fetch.
     *
     * @throws FacebookGraphException
     */
    public function publish(int $orgId, ?string $caption, ?string $imageUrl = null, ?string $videoUrl = null, bool $reel = false): string
    {
        $connection = $this->connectionOrFail($orgId);
        $account = $this->account($orgId);

        if (! $account || ! ($account['linked'] ?? false)) {
            throw new FacebookGraphException('No hay una cuenta de Instagram vinculada a la Página');
        }

        if (! $imageUrl && ! $videoUrl) {
            throw new FacebookGraphException('Indica la URL de una imagen o un video');
        }

        $params = ['caption' => (string) $caption];

        if ($videoUrl) {
            $params['video_url'] = $videoUrl;

            if ($reel) {
                $params['media_type'] = 'REELS';
            }
        } else {
            $params['image_url'] = $imageUrl;
        }

        $container = $this->graph->post($account['id'].'/media', $connection->access_token, $params);
        $containerId = (string) ($container['id'] ?? '');

        if (! $containerId) {
            throw new FacebookGraphException('Instagram no devolvió el contenedor de publicación');
        }

        if ($videoUrl) {
            $this->awaitContainer($containerId, $connection->access_token);
        }

        $raw = $this->graph->post($account['id'].'/media_publish', $connection->access_token, [
            'creation_id' => $containerId,
        ]);

        $this->clearCaches($orgId);

        return (string) ($raw['id'] ?? '');
    }

    /**
     * Delete own media permanently. Clears the local caches.
     *
     * @throws FacebookGraphException
     */
    public function deleteMedia(int $orgId, string $mediaId): void
    {
        $connection = $this->connectionOrFail($orgId);

        $this->graph->delete($mediaId, $connection->access_token);

        $this->clearCaches($orgId);
    }

    /**
     * Poll the publish container until Instagram finishes processing the
     * video (FINISHED/PUBLISHED), fails (ERROR) or the attempts run out.
     * Acotado a ~12 s en total (3 intentos, 4 s entre ellos): la publicación
     * es síncrona desde la UI y no debe colgar la petición.
     */
    private function awaitContainer(string $containerId, string $token): void
    {
        for ($try = 0; $try < 3; $try++) {
            $status = $this->graph->get($containerId, $token, [
                'fields' => 'status_code,status',
            ]);

            $code = (string) ($status['status_code'] ?? '');

            if ($code === 'FINISHED' || $code === 'PUBLISHED') {
                return;
            }

            if ($code === 'ERROR') {
                throw new FacebookGraphException('Instagram no pudo procesar el video: '.($status['status'] ?? 'error'));
            }

            if ($code === '') {
                throw new FacebookGraphException('No se pudo consultar el estado del video');
            }

            if ($try < 2) {
                sleep(4);
            }
        }

        throw new FacebookGraphException('Instagram sigue procesando el video, vuelve a intentarlo en unos minutos');
    }

    /**
     * Split [start, until] into consecutive windows of at most 30 days
     * (Instagram's since/until cap). Integer timestamps avoid a spurious
     * microsecond-sized tail window. Boundaries are shared on purpose —
     * merged values are deduplicated by metric+date upstream.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function insightWindows(\DateTimeInterface $start, \DateTimeInterface $until): array
    {
        $cursor = $start->getTimestamp();
        $end = $until->getTimestamp();
        $chunk = 30 * 86400;
        $windows = [];

        while ($cursor < $end) {
            $next = min($cursor + $chunk, $end);
            $windows[] = [$cursor, $next];

            if ($next >= $end) {
                break;
            }

            $cursor = $next;
        }

        return $windows;
    }

    /**
     * Metric sets with the already-resolved one first so the chain is not
     * retried from scratch on every window.
     *
     * @return array<int, string>
     */
    private function preferredFirst(array $metricSets, ?string $resolved): array
    {
        if ($resolved === null) {
            return $metricSets;
        }

        return array_values(array_unique(array_merge([$resolved], $metricSets)));
    }

    private function connectionOrFail(int $orgId): SocialConnection
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        return $connection;
    }

    private function clearCaches(int $orgId): void
    {
        Cache::forget("instagram_account:{$orgId}");
        Cache::forget("instagram_feed:{$orgId}");
        Cache::forget("instagram_comments:{$orgId}");
    }

    private function mapMedia(array $items): array
    {
        return array_map(fn (array $media) => [
            'id' => $media['id'] ?? null,
            'caption' => $media['caption'] ?? '',
            'type' => $media['media_type'] ?? null,
            'media_url' => $media['media_url'] ?? null,
            'thumbnail_url' => $media['thumbnail_url'] ?? null,
            'permalink' => $media['permalink'] ?? null,
            'timestamp' => $media['timestamp'] ?? null,
            'likes' => (int) ($media['like_count'] ?? 0),
            'comments' => (int) ($media['comments_count'] ?? 0),
            'username' => $media['username'] ?? null,
        ], array_values($items));
    }

    private function mapInsights(array $items): array
    {
        $metricMap = [
            'reach' => 'reach',
            'impressions' => 'impressions',
            'profile_views' => 'profile_views',
            'follower_count' => 'followers',
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
        $totals = ['reach' => 0, 'impressions' => 0, 'followers' => 0, 'profile_views' => 0];

        foreach ($byDate as $date => $row) {
            $entry = [
                'date' => $date,
                'reach' => $row['reach'] ?? 0,
                'impressions' => $row['impressions'] ?? 0,
                // follower_count is a level metric: keep the latest value,
                // not the sum of daily samples.
                'followers' => $row['followers'] ?? 0,
                'profile_views' => $row['profile_views'] ?? 0,
            ];
            $days[] = $entry;

            foreach (['reach', 'impressions', 'profile_views'] as $key) {
                $totals[$key] += $entry[$key];
            }

            $totals['followers'] = $entry['followers'];
        }

        return ['days' => $days, 'totals' => $totals];
    }
}
