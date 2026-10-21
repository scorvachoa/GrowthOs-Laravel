<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommentsService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly AuthService $auth,
    ) {}

    /**
     * Comment threads on the latest page posts (2 min cache, 60s negative
     * cache). Returns null when the org has no connection, or
     * ['threads' => [], 'total' => 0, 'unresponded' => 0, 'error' => true]
     * when the Graph API fails.
     */
    public function threads(int $orgId, int $limit = 10): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "facebook_comments:{$orgId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $raw = $this->graph->get($connection->page_id.'/feed', $connection->access_token, [
                'fields' => 'id,message,story,permalink_url,created_time,full_picture,comments.limit(20){id,message,from{id,name,picture.type(small)},created_time,likes.summary(true),can_reply,is_hidden,parent{id},comments.limit(10){id,message,from{id,name,picture.type(small)},created_time,likes.summary(true)}}',
                'limit' => $limit,
            ]);

            $result = $this->mapThreads($raw['data'] ?? [], $connection->page_id);
            Cache::put($cacheKey, $result, 120);
        } catch (Throwable $e) {
            Log::warning('Facebook comments fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['threads' => [], 'total' => 0, 'unresponded' => 0, 'error' => true];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Reply to a comment as the connected page. Requires a token with the
     * pages_manage_engagement permission. Clears the threads cache so the
     * reply shows up on the next fetch.
     *
     * @throws FacebookGraphException
     */
    public function reply(int $orgId, string $commentId, string $message): void
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $this->graph->post($commentId.'/comments', $connection->access_token, [
            'message' => $message,
        ]);

        Cache::forget("facebook_comments:{$orgId}");
    }

    /**
     * Hide or unhide a comment on a page post (is_hidden, reversible;
     * needs a Page token with pages_manage_engagement). Clears the
     * threads cache so the new state shows up on the next fetch.
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
            'is_hidden' => $hidden,
        ]);

        Cache::forget("facebook_comments:{$orgId}");
    }

    /**
     * Permanently delete a comment from a page post
     * (DELETE /{comment-id} with pages_manage_engagement). Clears the
     * threads cache.
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

        Cache::forget("facebook_comments:{$orgId}");
    }

    private function mapThreads(array $posts, string $pageId): array
    {
        $threads = [];

        foreach ($posts as $post) {
            $postContext = [
                'id' => $post['id'] ?? null,
                'snippet' => mb_substr(trim($post['message'] ?? $post['story'] ?? ''), 0, 120),
                'permalink_url' => $post['permalink_url'] ?? null,
                'picture' => $post['full_picture'] ?? null,
            ];

            foreach ($post['comments']['data'] ?? [] as $comment) {
                $authorId = (string) ($comment['from']['id'] ?? '');

                // The page's own top-level comments are responses, not inbox items.
                if ($authorId === (string) $pageId || isset($comment['parent'])) {
                    continue;
                }

                $replies = [];

                foreach ($comment['comments']['data'] ?? [] as $reply) {
                    $replies[] = $this->mapComment($reply, $pageId);
                }

                usort($replies, fn (array $a, array $b) => strcmp($a['created_time'] ?? '', $b['created_time'] ?? ''));

                $responded = false;

                foreach ($replies as $reply) {
                    if ($reply['author_is_page']) {
                        $responded = true;
                        break;
                    }
                }

                $threads[] = array_merge($this->mapComment($comment, $pageId), [
                    'replies' => $replies,
                    'responded' => $responded,
                    'post' => $postContext,
                ]);
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

    private function mapComment(array $comment, string $pageId): array
    {
        $from = $comment['from'] ?? [];

        return [
            'id' => $comment['id'] ?? null,
            'message' => $comment['message'] ?? '',
            'author' => $from['name'] ?? 'Usuario de Facebook',
            'author_picture' => $from['picture']['data']['url'] ?? null,
            'author_is_page' => (string) ($from['id'] ?? '') === (string) $pageId,
            'created_time' => $comment['created_time'] ?? null,
            'likes' => (int) ($comment['likes']['summary']['count'] ?? 0),
            'can_reply' => (bool) ($comment['can_reply'] ?? true),
            'is_hidden' => (bool) ($comment['is_hidden'] ?? false),
        ];
    }
}
