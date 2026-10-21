<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessagesService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly AuthService $auth,
    ) {}

    /**
     * Conversations of the page (60s cache, 60s negative cache), filtered
     * by platform ('messenger' or 'instagram') when given. Returns null
     * when the org has no connection, or
     * ['conversations' => [], 'error' => true] when the Graph API fails.
     */
    public function conversations(int $orgId, ?string $platform = null): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "facebook_conversations:{$orgId}".($platform !== null ? ":{$platform}" : '');
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $params = [
                'fields' => 'id,snippet,updated_time,message_count,unread_count,participants',
                'limit' => 25,
            ];

            if ($platform !== null) {
                $params['platform'] = $platform;
            }

            $raw = $this->graph->get($connection->page_id.'/conversations', $connection->access_token, $params);

            $result = [
                'conversations' => $this->mapConversations($raw['data'] ?? [], $connection->page_id),
                'error' => false,
            ];
            Cache::put($cacheKey, $result, 60);
        } catch (Throwable $e) {
            Log::warning('Facebook conversations fetch failed', [
                'organization_id' => $orgId,
                'error' => $e->getMessage(),
            ]);

            $result = ['conversations' => [], 'error' => true, 'error_message' => $e->getMessage()];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Messages of one conversation (30s cache, 60s negative cache).
     * conversation_id must be validated by the caller (regex).
     */
    public function messages(int $orgId, string $conversationId): ?array
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            return null;
        }

        $cacheKey = "facebook_messages:{$orgId}:{$conversationId}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $raw = $this->graph->get($conversationId.'/messages', $connection->access_token, [
                'fields' => 'id,from{id,name},message,created_time',
                'limit' => 50,
            ]);

            $result = [
                'messages' => $this->mapMessages($raw['data'] ?? [], $connection->page_id),
                'error' => false,
            ];
            Cache::put($cacheKey, $result, 30);
        } catch (Throwable $e) {
            Log::warning('Facebook messages fetch failed', [
                'organization_id' => $orgId,
                'conversation' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            $result = ['messages' => [], 'error' => true, 'error_message' => $e->getMessage()];
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Send a text reply into an existing conversation as the page
     * (POST /{page-id}/messages with the participant PSID). Requires the
     * pages_messaging permission. Clears the conversation caches.
     *
     * @throws FacebookGraphException
     */
    public function send(int $orgId, string $conversationId, string $text): void
    {
        $connection = $this->auth->connection($orgId);

        if (! $connection) {
            abort(403, 'No hay una Página conectada');
        }

        $conversation = $this->graph->get($conversationId, $connection->access_token, [
            'fields' => 'participants',
        ]);

        $recipientId = null;

        foreach ($conversation['participants']['data'] ?? [] as $participant) {
            if (($participant['id'] ?? null) !== $connection->page_id) {
                $recipientId = $participant['id'] ?? null;
                break;
            }
        }

        if (! $recipientId) {
            $participants = $conversation['participants']['data'] ?? [];
            $recipientId = $participants[0]['id'] ?? null;
        }

        if (! $recipientId) {
            throw new FacebookGraphException('No se encontró el destinatario de la conversación');
        }

        $this->graph->post($connection->page_id.'/messages', $connection->access_token, [
            'recipient' => json_encode(['id' => $recipientId]),
            'message' => json_encode(['text' => $text]),
            'messaging_type' => 'RESPONSE',
        ]);

        Cache::forget("facebook_conversations:{$orgId}");
        Cache::forget("facebook_conversations:{$orgId}:messenger");
        Cache::forget("facebook_conversations:{$orgId}:instagram");
        Cache::forget("facebook_messages:{$orgId}:{$conversationId}");
    }

    private function mapConversations(array $items, string $pageId): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $participants = [];
            $title = null;

            foreach ($item['participants']['data'] ?? [] as $participant) {
                $participants[] = [
                    'id' => $participant['id'] ?? null,
                    'name' => $participant['name'] ?? null,
                ];

                if (! $title && ($participant['id'] ?? null) !== $pageId && ! empty($participant['name'])) {
                    $title = $participant['name'];
                }
            }

            $mapped[] = [
                'id' => $item['id'] ?? null,
                'snippet' => $item['snippet'] ?? '',
                'updated_time' => $item['updated_time'] ?? null,
                'message_count' => (int) ($item['message_count'] ?? 0),
                'unread_count' => (int) ($item['unread_count'] ?? 0),
                'title' => $title ?? ($participants[0]['name'] ?? 'Conversación'),
                'participants' => $participants,
            ];
        }

        usort($mapped, fn (array $a, array $b) => strcmp($b['updated_time'] ?? '', $a['updated_time'] ?? ''));

        return $mapped;
    }

    private function mapMessages(array $items, string $pageId): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $mapped[] = [
                'id' => $item['id'] ?? null,
                'message' => $item['message'] ?? '',
                'created_time' => $item['created_time'] ?? null,
                'from_name' => $item['from']['name'] ?? null,
                'is_page' => ($item['from']['id'] ?? null) === $pageId,
            ];
        }

        usort($mapped, fn (array $a, array $b) => strcmp($a['created_time'] ?? '', $b['created_time'] ?? ''));

        return $mapped;
    }
}
