<?php

namespace App\Jobs;

use App\Models\GeneratedVideo;
use App\Services\AI\AIContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAIContent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $input,
        public int $videoId,
    ) {}

    public function handle(AIContentService $ai): void
    {
        try {
            $video = GeneratedVideo::query()->withoutGlobalScopes()->findOrFail($this->videoId);

            $data = match ($this->type) {
                'script' => $this->generateScript($ai),
                'copy' => $this->generateCopy($ai),
                'phrases' => $this->generatePhrases($ai),
                'copy-phrases' => $this->generateCopyPhrases($ai),
                default => throw new \InvalidArgumentException("Unknown type: {$this->type}"),
            };

            $data['status'] = 'completed';
            $video->update($data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->fail(new \RuntimeException("Video {$this->videoId} not found for AI processing"));
        } catch (\Throwable $e) {
            try {
                $video = GeneratedVideo::query()->withoutGlobalScopes()->find($this->videoId);
                $video?->update(['status' => 'failed']);
            } finally {
                $this->fail($e);
            }
        }
    }

    private function generateScript(AIContentService $ai): array
    {
        return ['script' => $ai->generateScript($this->input)];
    }

    private function generateCopy(AIContentService $ai): array
    {
        $copy = $ai->generateCopy($this->input);
        return [
            'copy_title' => $copy['title'],
            'copy_description' => $copy['description'],
            'copy_cta' => $copy['cta'],
            'copy_hashtags' => $copy['hashtags'],
            'copy_tags' => $copy['tags'],
        ];
    }

    private function generatePhrases(AIContentService $ai): array
    {
        return ['video_phrases' => $ai->generatePhrases($this->input)];
    }

    private function generateCopyPhrases(AIContentService $ai): array
    {
        $copy = $ai->generateCopy($this->input);
        $phrases = $ai->generatePhrases($this->input);
        return [
            'copy_title' => $copy['title'],
            'copy_description' => $copy['description'],
            'copy_cta' => $copy['cta'],
            'copy_hashtags' => $copy['hashtags'],
            'copy_tags' => $copy['tags'],
            'video_phrases' => $phrases,
        ];
    }
}
