<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class ElevenLabsService
{
    private const API_URL = 'https://api.elevenlabs.io/v1/text-to-speech';
    private const DEFAULT_MODEL_ID = 'eleven_multilingual_v2';
    private const DEFAULT_OUTPUT_FORMAT = 'mp3_44100_128';
    private const REQUEST_TIMEOUT = 90;

    public function generateAudio(string $script): \Illuminate\Http\Client\Response
    {
        $apiKey = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', self::DEFAULT_MODEL_ID);

        if (!$apiKey || !$voiceId) {
            throw new \RuntimeException('Falta configurar ELEVENLABS_API_KEY o ELEVENLABS_VOICE_ID en el archivo .env.');
        }

        $url = self::API_URL . "/{$voiceId}?output_format=" . self::DEFAULT_OUTPUT_FORMAT;

        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg',
            ])
            ->post($url, [
                'text' => trim($script),
                'model_id' => $modelId,
            ]);

        if ($response->failed()) {
            $detail = $response->body();
            throw new \RuntimeException(
                "ElevenLabs devolvió un error al generar el audio. " . ($detail ?: $response->status())
            );
        }

        if (empty($response->body())) {
            throw new \RuntimeException('ElevenLabs devolvió un audio vacío.');
        }

        return $response;
    }
}
