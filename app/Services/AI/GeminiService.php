<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const MODEL_NAME = 'gemini-2.5-flash';
    private const REQUEST_TIMEOUT = 45;
    private const MAX_RETRIES_PER_KEY = 2;

    private array $keys;
    private int $totalKeys;
    private int $currentIndex = 0;

    public function __construct()
    {
        $this->keys = config('services.gemini.api_keys', []);
        $this->totalKeys = count($this->keys);

        if ($this->totalKeys === 0) {
            throw new \RuntimeException(
                'Configura GEMINI_API_KEY, GEMINI_KEY_1, etc. en el archivo .env.'
            );
        }
    }

    public function generateContent(string $prompt): string
    {
        $errors = [];

        for ($attempt = 0; $attempt < $this->totalKeys; $attempt++) {
            $key = $this->nextKey();
            try {
                return $this->callWithRetry($prompt, $key);
            } catch (\Exception $e) {
                $errorType = $this->isRateLimitError($e) ? 'rate-limit' : get_class($e);
                $errors[] = "key #{$this->currentIndex}: {$errorType}";
                Log::warning("Gemini key failed: {$e->getMessage()}");
                continue;
            }
        }

        $details = implode('; ', array_slice($errors, -6));
        throw new \RuntimeException("No fue posible generar contenido con las API keys disponibles. {$details}");
    }

    private function callWithRetry(string $prompt, string $key): string
    {
        $lastError = null;

        for ($retry = 0; $retry < self::MAX_RETRIES_PER_KEY; $retry++) {
            try {
                return $this->callGemini($prompt, $key);
            } catch (\Exception $e) {
                $lastError = $e;
                if ($retry < self::MAX_RETRIES_PER_KEY - 1) {
                    usleep(700000 * ($retry + 1));
                }
            }
        }

        throw $lastError;
    }

    private function callGemini(string $prompt, string $apiKey): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL_NAME . ":generateContent?key={$apiKey}";

        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.85,
                    'topP' => 0.95,
                    'maxOutputTokens' => 4096,
                ],
            ]);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("Gemini API error ({$status}): {$body}");
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new \RuntimeException('Gemini devolvió una respuesta vacía.');
        }

        return trim($text);
    }

    private function nextKey(): string
    {
        $key = $this->keys[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % $this->totalKeys;
        return $key;
    }

    private function isRateLimitError(\Exception $e): bool
    {
        $message = mb_strtolower($e->getMessage());
        $markers = ['429', 'rate', 'quota', 'resource_exhausted', 'too many requests'];
        foreach ($markers as $marker) {
            if (str_contains($message, $marker)) {
                return true;
            }
        }
        return false;
    }
}
