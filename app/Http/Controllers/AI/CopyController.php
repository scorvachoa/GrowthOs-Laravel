<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\GeneratedVideo;
use App\Services\AI\AIContentService;
use Illuminate\Http\Request;

class CopyController extends Controller
{
    public function __construct(
        protected AIContentService $ai,
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

        try {
            $copy = $this->ai->generateCopy($script);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al generar copy: ' . $e->getMessage()], 502);
        }

        $video = $this->resolveVideo($validated, $idea, $script, $copy);

        return response()->json([
            'video_id' => $video->id,
            'idea' => $video->idea,
            'script' => $video->script,
            'copy' => $copy,
        ], 201);
    }

    private function resolveVideo(array $validated, string $idea, string $script, array $copy): GeneratedVideo
    {
        if (!empty($validated['video_id'])) {
            $video = GeneratedVideo::find($validated['video_id']);
            if ($video) {
                $video->update([
                    'copy_title' => $copy['title'],
                    'copy_description' => $copy['description'],
                    'copy_cta' => $copy['cta'],
                    'copy_hashtags' => $copy['hashtags'],
                    'copy_tags' => $copy['tags'],
                ]);
                return $video;
            }
        }

        return GeneratedVideo::create([
            'idea' => $idea ?: 'Guion editado manualmente',
            'script' => $script,
            'copy_title' => $copy['title'],
            'copy_description' => $copy['description'],
            'copy_cta' => $copy['cta'],
            'copy_hashtags' => $copy['hashtags'],
            'copy_tags' => $copy['tags'],
        ]);
    }
}
