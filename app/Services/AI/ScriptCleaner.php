<?php

namespace App\Services\AI;

class ScriptCleaner
{
    private const HEADING_PATTERNS = [
        '/^\s*#{1,6}\s+.*$/m',
        '/^\s*[-–—]{3,}\s*$/m',
        '/^\s*\*?\*?(?:guion|duracion|hook|desarrollo|dato clave|cierre|cta|texto para miniatura).*?\*?\*?\s*$/im',
        '/^\s*(?:🎯|🧭|🤯|✅|🖼️).*?$/mu',
        '/^\s*(?:guion para|duracion:|hook\b|desarrollo\b|dato clave\b|cierre\b|cta\b|texto para miniatura\b).*?$/im',
    ];

    public function clean(string $rawScript): string
    {
        $text = preg_replace('/^\s*(?:¡?absolutamente!?|claro[,.!]?|aqui tienes.*?|te presento.*?)(?:\n|$)/im', '', trim($rawScript));

        $lines = explode("\n", $text);
        $cleanLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $normalized = mb_strtolower(trim($line, " ¡!¿? "));
            if (str_starts_with($normalized, 'absolutamente') ||
                str_starts_with($normalized, 'claro') ||
                str_starts_with($normalized, 'aqui tienes') ||
                str_starts_with($normalized, 'te presento')) {
                continue;
            }

            if (str_contains($normalized, 'texto para miniatura')) {
                break;
            }

            if (preg_match('/^\s*\*?\*?duracion\*?\*?\s*:/i', $line)) {
                continue;
            }

            $isHeading = false;
            foreach (self::HEADING_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    $isHeading = true;
                    break;
                }
            }
            if ($isHeading) {
                continue;
            }

            $line = preg_replace('/\*\*(.*?)\*\*/', '$1', $line);
            $line = preg_replace('/\([^)]*\)/', '', $line);
            $line = preg_replace('/^\s*[-*•]\s*/', '', $line);
            $line = preg_replace('/^\s*\d+\.\s*/', '', $line);
            $line = trim($line);

            if ($line !== '') {
                $cleanLines[] = $line;
            }
        }

        return implode("\n", $cleanLines);
    }
}
