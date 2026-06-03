<?php

namespace App\Services\AI;

class PhraseCleaner
{
    private const SKIP_PREFIXES = [
        'claro', 'aqui tienes', 'te presento', 'por supuesto',
        'lista', 'frases clave', 'frases mas fuertes', 'frases más fuertes',
        'hooks', 'hooks visuales', 'recomendados', 'momentos',
    ];

    private const SKIP_WORDS = ['zoom', 'drone', 'transicion', 'transición', 'música', 'musica', 'glitch', 'cámara', 'camara'];

    public function clean(string $rawPhrases): string
    {
        $cleanLines = [];
        $seen = [];

        foreach (explode("\n", $rawPhrases) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            $normalized = mb_strtolower(trim($line, " ¡!¿?:.-*"));

            $shouldSkip = false;
            foreach (self::SKIP_PREFIXES as $prefix) {
                if (str_starts_with($normalized, $prefix)) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) {
                continue;
            }

            foreach (self::SKIP_WORDS as $word) {
                if (str_contains($normalized, $word)) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) {
                continue;
            }

            $line = preg_replace('/\*\*(.*?)\*\*/', '$1', $line);
            $line = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2700}-\x{27BF}\x{2600}-\x{26FF}]/u', '', $line);
            $line = str_replace(['️', '︎'], '', $line);
            $line = preg_replace('/^\s*[-*•]+\s*/', '', $line);
            $line = preg_replace('/^\s*\d+[.)]\s*/', '', $line);
            $line = preg_replace('/\([^)]*\)/', '', $line);
            $line = str_replace('*', '', $line);
            $line = trim($line, " -–—\t");
            $line = preg_replace('/\s+/', ' ', $line);
            $line = trim($line);

            if ($line !== '' && mb_strlen($line) <= 90) {
                $upper = mb_strtoupper($line);
                if (!isset($seen[$upper])) {
                    $seen[$upper] = true;
                    $cleanLines[] = $upper;
                }
            }
        }

        return implode("\n", $cleanLines);
    }
}
