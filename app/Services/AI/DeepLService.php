<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class DeepLService
{
    private const TIMEOUT = 15;

    private const TARGET_MAP = [
        'es' => 'ES',
        'en' => 'EN-US',
        'pt' => 'PT-PT',
        'fr' => 'FR',
        'de' => 'DE',
        'it' => 'IT',
        'ja' => 'JA',
        'ko' => 'KO',
        'zh' => 'ZH',
    ];

    public function supportedTargets(): array
    {
        return array_keys(self::TARGET_MAP);
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    public function translate(array $texts, string $targetLang): array
    {
        $apiKey = trim((string) config('services.deepl.api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException('DEEPL_API_KEY no está configurada en .env.');
        }

        $target = self::TARGET_MAP[$targetLang] ?? strtoupper($targetLang);

        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$apiKey])
            ->post($this->endpoint($apiKey), [
                'text' => array_values($texts),
                'target_lang' => $target,
                'preserve_formatting' => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('DeepL API error ('.$response->status().'): '.$response->body());
        }

        $translations = $response->json('translations');

        if (! is_array($translations) || count($translations) !== count($texts)) {
            throw new \RuntimeException('DeepL devolvió una respuesta inesperada.');
        }

        return array_map(fn ($t) => (string) ($t['text'] ?? ''), $translations);
    }

    private function endpoint(string $apiKey): string
    {
        return str_ends_with($apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
    }
}
