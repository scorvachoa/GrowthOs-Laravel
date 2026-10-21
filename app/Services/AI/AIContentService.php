<?php

namespace App\Services\AI;

class AIContentService
{
    public function __construct(
        protected GeminiService $gemini,
        protected ScriptCleaner $scriptCleaner,
        protected CopyParser $copyParser,
        protected PhraseCleaner $phraseCleaner,
        protected TitlesThumbnailsParser $titlesThumbnailsParser,
    ) {}

    public function generateScript(string $idea): string
    {
        $prompt = Prompts::buildScriptPrompt($idea);
        $rawScript = $this->gemini->generateContent($prompt);

        return $this->scriptCleaner->clean($rawScript);
    }

    public function generateCopy(string $script): array
    {
        $prompt = Prompts::buildCopyPrompt($script);
        $rawCopy = $this->gemini->generateContent($prompt);

        return $this->copyParser->parse($rawCopy);
    }

    public function generatePhrases(string $script): string
    {
        $prompt = Prompts::buildPhrasesPrompt($script);
        $rawPhrases = $this->gemini->generateContent($prompt);

        return $this->phraseCleaner->clean($rawPhrases);
    }

    public function generateTitlesAndThumbnails(string $script, string $title = ''): array
    {
        $prompt = Prompts::buildTitlesThumbnailsPrompt($script, $title);
        $raw = $this->gemini->generateContent($prompt);

        return $this->titlesThumbnailsParser->parse($raw);
    }

    public function generateWeeklySummary(array $context): string
    {
        return $this->gemini->generateContent(Prompts::buildWeeklySummaryPrompt($context));
    }

    public function generateIdeas(string $channelName, array $existingIdeas = [], int $count = 10): array
    {
        $prompt = Prompts::buildIdeasPrompt($channelName, $existingIdeas, $count);
        $raw = $this->gemini->generateContent($prompt);

        $existingLookup = array_map(fn ($idea) => mb_strtolower(trim($idea)), $existingIdeas);
        $ideas = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^\s*(?:\d+[\.\)]|[-*•])\s*/u', '', $line) ?? $line;
            $line = preg_replace('/\*\*(.*?)\*\*/', '$1', $line) ?? $line;
            $line = trim($line, " \t.\"");

            if (mb_strlen($line) < 10 || mb_strlen($line) > 200) {
                continue;
            }

            if (in_array(mb_strtolower($line), $existingLookup, true)) {
                continue;
            }

            $ideas[] = $line;
        }

        return array_values(array_unique($ideas));
    }
}
