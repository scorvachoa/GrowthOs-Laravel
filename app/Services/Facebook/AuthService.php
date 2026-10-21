<?php

namespace App\Services\Facebook;

use App\Models\SocialConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthService
{
    public function __construct(private readonly GraphClient $graph) {}

    public function connect(string $token, int $orgId): SocialConnection
    {
        $existing = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('provider', 'facebook')
            ->first();

        $longLived = $this->exchangeLongLived($token);
        $effectiveToken = $longLived['token'] ?? $token;

        try {
            $page = $this->fetchPage($effectiveToken, $existing?->page_id);
        } catch (FacebookGraphException $e) {
            throw ValidationException::withMessages([
                'facebook_token' => 'No es un token válido de Página de Facebook: '.$e->getMessage(),
            ]);
        }

        $connection = SocialConnection::withoutGlobalScope('organization')->updateOrCreate(
            ['organization_id' => $orgId, 'provider' => 'facebook'],
            [
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'page_picture' => $page['picture'],
                'access_token' => $page['token'] ?? $effectiveToken,
                'user_token' => $longLived['token'] ?? null,
                'expires_at' => $longLived ? now()->addSeconds($longLived['expires_in'] ?? 5184000) : null,
                'meta' => [
                    'followers' => $page['followers'],
                    'fan_count' => $page['fan_count'],
                    'connected_at' => now()->toIso8601String(),
                ],
            ]
        );

        Cache::forget($this->cacheKey($orgId));
        $this->forgetInstagramCaches($orgId);

        return $connection;
    }

    public function disconnect(int $orgId): void
    {
        SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('provider', 'facebook')
            ->delete();

        Cache::forget($this->cacheKey($orgId));
        $this->forgetInstagramCaches($orgId);
    }

    public function connection(int $orgId): ?SocialConnection
    {
        $connection = SocialConnection::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('provider', 'facebook')
            ->first();

        if (! $connection) {
            return null;
        }

        return $this->refreshIfDue($connection);
    }

    /**
     * Exchange a valid user token for a long-lived one (60 days) using the
     * App Secret. Returns null when the app credentials are missing, the
     * token cannot be exchanged (e.g. it is already a Page token) or Graph
     * rejects it — the caller then falls back to the original token.
     */
    private function exchangeLongLived(string $token): ?array
    {
        $clientId = config('services.facebook.client_id');
        $clientSecret = config('services.facebook.client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $version = config('services.facebook.graph_version', 'v22.0');

        try {
            $response = Http::timeout(10)->retry(2, 300, throw: false)->get('https://graph.facebook.com/'.$version.'/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'fb_exchange_token' => $token,
            ]);
        } catch (Throwable $e) {
            Log::warning('Facebook token exchange failed', ['error' => $e->getMessage()]);

            return null;
        }

        $body = is_array($response->json()) ? $response->json() : [];

        if (! $response->successful() || isset($body['error']) || empty($body['access_token'])) {
            Log::info('Facebook token exchange skipped', [
                'status' => $response->status(),
                'message' => $body['error']['message'] ?? 'sin access_token en la respuesta',
            ]);

            return null;
        }

        return [
            'token' => (string) $body['access_token'],
            'expires_in' => (int) ($body['expires_in'] ?? 5184000),
        ];
    }

    /**
     * Refresh the long-lived user token (and the page token derived from it)
     * when it is within 7 days of expiring. Connections without a stored user
     * token (legacy short-lived paste) are returned untouched, so a refresh
     * never happens without an App Secret round-trip being possible.
     */
    private function refreshIfDue(SocialConnection $connection): SocialConnection
    {
        if (! $connection->user_token || ! $connection->expires_at) {
            return $connection;
        }

        if ($connection->expires_at->greaterThan(now()->addDays(7))) {
            return $connection;
        }

        $fresh = $this->exchangeLongLived($connection->user_token);

        if (! $fresh) {
            Log::warning('Facebook token refresh skipped', [
                'organization_id' => $connection->organization_id,
            ]);

            return $connection;
        }

        try {
            $page = $this->fetchPage($fresh['token'], $connection->page_id);
        } catch (Throwable $e) {
            Log::warning('Facebook token refresh failed', [
                'organization_id' => $connection->organization_id,
                'error' => $e->getMessage(),
            ]);

            return $connection;
        }

        $connection->update([
            'access_token' => $page['token'] ?? $connection->access_token,
            'user_token' => $fresh['token'],
            'expires_at' => now()->addSeconds($fresh['expires_in'] ?? 5184000),
        ]);

        Cache::forget($this->cacheKey($connection->organization_id));
        $this->forgetInstagramCaches($connection->organization_id);

        return $connection;
    }

    /**
     * Fresh page info (10 min cache). On Graph failure falls back to the
     * stored snapshot flagged as stale instead of failing the page render.
     */
    public function pageInfo(int $orgId): ?array
    {
        $connection = $this->connection($orgId);

        if (! $connection) {
            return null;
        }

        return Cache::remember($this->cacheKey($orgId), now()->addMinutes(10), function () use ($connection) {
            try {
                $page = $this->fetchPage($connection->access_token, $connection->page_id);

                $connection->update([
                    'page_name' => $page['name'],
                    'page_picture' => $page['picture'] ?? $connection->page_picture,
                    'meta' => array_merge($connection->meta ?? [], [
                        'followers' => $page['followers'],
                        'fan_count' => $page['fan_count'],
                    ]),
                ]);

                return $this->toArray($connection, stale: false);
            } catch (Throwable $e) {
                Log::warning('Facebook page info refresh failed', [
                    'organization_id' => $connection->organization_id,
                    'error' => $e->getMessage(),
                ]);

                return $this->toArray($connection, stale: true);
            }
        });
    }

    /**
     * Page data for a stored/pasted token. A user access token is resolved
     * through /me/accounts (picking the preferred page when reconnecting) and
     * its derived page token is returned; page tokens have no `accounts` field
     * so they fall through to the direct `me` lookup.
     */
    private function fetchPage(string $token, ?string $preferredPageId = null): array
    {
        $pages = null;

        try {
            $accounts = $this->graph->get('me/accounts', $token, [
                'fields' => 'id,name,picture{url},access_token,followers_count,fan_count',
                'limit' => 20,
            ]);

            if (isset($accounts['data']) && is_array($accounts['data'])) {
                $pages = $accounts['data'];
            }
        } catch (FacebookGraphException) {
            // Page token (or invalid token) — handled by the direct lookup below.
        }

        if ($pages !== null) {
            if ($pages === []) {
                throw new FacebookGraphException('El token de usuario no tiene Páginas asociadas');
            }

            $page = null;

            foreach ($pages as $candidate) {
                if ($preferredPageId !== null && ($candidate['id'] ?? null) === $preferredPageId) {
                    $page = $candidate;
                    break;
                }
            }

            $page = $page ?? $pages[0];

            if (empty($page['id']) || empty($page['name'])) {
                throw new FacebookGraphException('El token no devolvió una Página válida');
            }

            return [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
                'picture' => $page['picture']['data']['url'] ?? null,
                'followers' => $page['followers_count'] ?? null,
                'fan_count' => $page['fan_count'] ?? null,
                'token' => $page['access_token'] ?? null,
            ];
        }

        $data = $this->graph->get('me', $token, [
            'fields' => 'id,name,picture{url},followers_count,fan_count',
        ]);

        if (empty($data['id']) || empty($data['name'])) {
            throw new FacebookGraphException('El token no devolvió una Página válida');
        }

        return [
            'id' => (string) $data['id'],
            'name' => (string) $data['name'],
            'picture' => $data['picture']['data']['url'] ?? null,
            'followers' => $data['followers_count'] ?? null,
            'fan_count' => $data['fan_count'] ?? null,
            'token' => null,
        ];
    }

    private function toArray(SocialConnection $connection, bool $stale): array
    {
        return [
            'connected' => true,
            'page_id' => $connection->page_id,
            'page_name' => $connection->page_name,
            'page_picture' => $connection->page_picture,
            'followers' => $connection->meta['followers'] ?? null,
            'fan_count' => $connection->meta['fan_count'] ?? null,
            'stale' => $stale,
            'connected_at' => $connection->meta['connected_at'] ?? null,
        ];
    }

    private function cacheKey(int $orgId): string
    {
        return "facebook_page_info:{$orgId}";
    }

    /**
     * Instagram data derives from this connection's token, so a reconnect
     * (e.g. adding the instagram_* scopes) or a disconnect must drop it.
     */
    private function forgetInstagramCaches(int $orgId): void
    {
        Cache::forget("instagram_account:{$orgId}");
        Cache::forget("instagram_feed:{$orgId}");
        Cache::forget("instagram_comments:{$orgId}");
    }
}
