<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GraphClient
{
    /**
     * GET against the Graph API. Returns the decoded payload on success,
     * throws FacebookGraphException on HTTP/Graph errors.
     */
    public function get(string $path, string $token, array $params = []): array
    {
        return $this->send('get', $path, $token, $params);
    }

    /**
     * POST against the Graph API. Returns the decoded payload on success,
     * throws FacebookGraphException on HTTP/Graph errors.
     */
    public function post(string $path, string $token, array $params = []): array
    {
        return $this->send('post', $path, $token, $params);
    }

    /**
     * DELETE against the Graph API (params sent as query string, as Graph
     * expects). Returns the decoded payload, throws FacebookGraphException.
     */
    public function delete(string $path, string $token, array $params = []): array
    {
        return $this->send('delete', $path, $token, $params);
    }

    private function send(string $method, string $path, string $token, array $params): array
    {
        $version = config('services.facebook.graph_version', 'v22.0');
        $url = 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
        $payload = array_merge($params, ['access_token' => $token]);

        try {
            $pending = Http::timeout(10)->retry(2, 300, throw: false);
            $response = match ($method) {
                'post' => $pending->post($url, $payload),
                'delete' => $pending->withQueryParameters($payload)->delete($url),
                default => $pending->get($url, $payload),
            };
        } catch (Throwable $e) {
            Log::warning('Facebook Graph API connection error', ['path' => $path, 'error' => $e->getMessage()]);

            throw new FacebookGraphException('No se pudo contactar con Facebook: '.$e->getMessage());
        }

        $body = is_array($response->json()) ? $response->json() : [];

        if (! $response->successful() || isset($body['error'])) {
            $message = $body['error']['message'] ?? 'Respuesta no válida de Facebook';

            Log::warning('Facebook Graph API error', [
                'path' => $path,
                'status' => $response->status(),
                'message' => $message,
            ]);

            throw new FacebookGraphException($message);
        }

        return $body;
    }
}
