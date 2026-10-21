<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstagramCommentsService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly AuthService $auth,
        private readonly InstagramService $instagram,
    ) {}

    /**
     * Comment threads on the latest media of the linked account (2 min
     * cache, 60s negative cache). Returns null when the org has no page
     * connection, a non-error empty list when no Instagram is linked, or
     * ['threads' => [], 'total' => 0, 'unresponded' => 0, 'error' => true]
     * when the Graph API fails.
     */
    public function threads(int $orgId, int $mediaLimit = 8): ?array
    {
        $account = $this->instagram->account($orgId);

        if ($account === null) {
            return null;
        }

        if (! ($account['linked'] ?? false)) {
            return ['threads' => [], 'total' => 0, 'unresponded' => 0, 'error' => (bool) ($account['error'] ?? false)];
        }

        $cacheKey = "instagram_comments:{$orgId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $connection = $this->auth->connection($orgId);

        try {
            $mediaRaw = $this->graph->get($account['id'].'/media', $connection->access_token, [
                'fields' => 'id,caption,media_url,thumbnail_url,permalink,timestamp',
                'limit' => $mediaLimit,
            ]);

            $result = $this->mapThreads($mediaRaw['data'] ?? [], $account, $connection->access_token);
            Cache::put($cacheKey, $result, 120);
        } catch (Throwable $e) {
            Log::warning('Instagram comments fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['threads' => [], 'total' => 0, 'unresponded' => 0, 'error' => true];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Reply to a comment as the connected Instagram account (needs
     * instagram_manage_comments). Clears the threads cache so the reply
     * shows up on the next fetch.
     *
     * @throws FacebookGraphException
     */
    public function reply(int $orgId, string $commentId, string $text): void
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $this->graph->post($commentId.'/replies', $connection->access_token, [
            'text' => $text,
        ]);

        Cache::forget("instagram_comments:{$orgId}");
    }

    /**
     * Hide or unhide a comment (POST /{comment-id} with hide=true|false,
     * needs instagram_manage_comments). Clears the threads cache.
     *
     * @throws FacebookGraphException
     */
    public function hide(int $orgId, string $commentId, bool $hidden): void
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $this->graph->post($commentId, $connection->access_token, [
            'hide' => $hidden,
        ]);

        Cache::forget("instagram_comments:{$orgId}");
    }

    /**
     * Permanently delete a comment (DELETE /{comment-id} with
     * instagram_manage_comments). Clears the threads cache.
     *
     * @throws FacebookGraphException
     */
    public function delete(int $orgId, string $commentId): void
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $this->graph->delete($commentId, $connection->access_token);

        Cache::forget("instagram_comments:{$orgId}");
    }

    /**
     * Like or unlike a comment as the linked Instagram account
     * (POST|DELETE /{ig-user-id}/likes with comment_id, needs
     * instagram_manage_engagement). Clears the threads cache.
     *
     * @throws FacebookGraphException
     */
    public function setLike(int $orgId, string $commentId, bool $liked): void
    {
        $account = $this->instagram->account($orgId);

        if ($account === null || ! ($account['linked'] ?? false)) {
            abort(403, 'No hay una cuenta de Instagram vinculada');
        }

        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $params = ['comment_id' => $commentId];

        if ($liked) {
            $this->graph->post($account['id'].'/likes', $connection->access_token, $params);
        } else {
            $this->graph->delete($account['id'].'/likes', $connection->access_token, $params);
        }

        Cache::forget("instagram_comments:{$orgId}");
    }

    private function mapThreads(array $mediaItems, array $account, string $token): array
    {
        $threads = [];
        $ownUsername = strtolower((string) ($account['username'] ?? ''));

        foreach ($mediaItems as $media) {
            $mediaId = $media['id'] ?? null;

            if (! $mediaId) {
                continue;
            }

            $commentsRaw = $this->graph->get($mediaId.'/comments', $token, [
                'fields' => 'id,text,timestamp,username,like_count,hidden,replies{id,text,timestamp,username,hidden}',
                'limit' => 20,
            ]);

            $postContext = [
                'id' => $mediaId,
                'snippet' => mb_substr(trim($media['caption'] ?? ''), 0, 120),
                'picture' => $media['media_url'] ?? $media['thumbnail_url'] ?? null,
                'permalink' => $media['permalink'] ?? null,
            ];

            foreach ($commentsRaw['data'] ?? [] as $comment) {
                $username = (string) ($comment['username'] ?? '');

                // The account's own top-level comments are not inbox items.
                if ($ownUsername !== '' && strtolower($username) === $ownUsername) {
                    continue;
                }

                $replies = [];
                $responded = false;

                foreach ($comment['replies']['data'] ?? [] as $reply) {
                    $replyOwn = $ownUsername !== '' && strtolower((string) ($reply['username'] ?? '')) === $ownUsername;

                    if ($replyOwn) {
                        $responded = true;
                    }

                    $replies[] = [
                        'id' => $reply['id'] ?? null,
                        'message' => $reply['text'] ?? '',
                        'author' => $reply['username'] ?? 'Usuario de Instagram',
                        'author_is_page' => $replyOwn,
                        'created_time' => $reply['timestamp'] ?? null,
                        'hidden' => (bool) ($reply['hidden'] ?? false),
                    ];
                }

                usort($replies, fn (array $a, array $b) => strcmp($a['created_time'] ?? '', $b['created_time'] ?? ''));

                $threads[] = [
                    'id' => $comment['id'] ?? null,
                    'message' => $comment['text'] ?? '',
                    'author' => $username ?: 'Usuario de Instagram',
                    'created_time' => $comment['timestamp'] ?? null,
                    'likes' => (int) ($comment['like_count'] ?? 0),
                    'hidden' => (bool) ($comment['hidden'] ?? false),
                    'replies' => $replies,
                    'responded' => $responded,
                    'can_reply' => true,
                    'post' => $postContext,
                ];
            }
        }

        usort($threads, fn (array $a, array $b) => strcmp($b['created_time'] ?? '', $a['created_time'] ?? ''));

        $unresponded = 0;

        foreach ($threads as $thread) {
            if (! $thread['responded']) {
                $unresponded++;
            }
        }

        return [
            'threads' => $threads,
            'total' => count($threads),
            'unresponded' => $unresponded,
            'error' => false,
        ];
    }
}
