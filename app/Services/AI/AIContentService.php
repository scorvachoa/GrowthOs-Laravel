<?php

namespace App\Services\AI;

class AIContentService
{
    public function __construct(
        protected GeminiService $gemini,
        protected ScriptCleaner $scriptCleaner,
        protected CopyParser $copyParser,
        protected PhraseCleaner $phraseCleaner,
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
}
