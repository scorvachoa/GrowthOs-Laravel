<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\GeneratedVideo;
use App\Services\AI\ElevenLabsService;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    public function __construct(
        protected ElevenLabsService $elevenLabs,
    ) {}

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'script' => ['required', 'string', 'min:10', 'max:5000'],
            'idea' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'integer', 'exists:generated_videos,id'],
        ]);

        $idea = trim($validated['idea'] ?? '');
        $script = trim($validated['script']);

        $video = null;
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
        }

        if ($video === null) {
            $video = GeneratedVideo::create([
                'idea' => $idea ?: 'Guion editado manualmente',
                'script' => $script,
            ]);
        }

        try {
            $response = $this->elevenLabs->generateAudio($script);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al generar audio: ' . $e->getMessage()], 502);
        }

        $filename = $this->safeFilename($idea ?: 'guion-audio');

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, "{$filename}.mp3", [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => "attachment; filename=\"{$filename}.mp3\"",
        ]);
    }

    private function safeFilename(string $idea): string
    {
        $base = preg_replace('/[^a-zA-Z0-9]+/', '-', trim($idea));
        $base = strtolower(trim($base, '-'));
        return mb_substr($base, 0, 50) ?: 'guion-audio';
    }
}
