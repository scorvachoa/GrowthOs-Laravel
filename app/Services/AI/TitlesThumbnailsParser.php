<?php

namespace App\Services\AI;

class TitlesThumbnailsParser
{
    public function parse(string $rawTitles): array
    {
        $titles = [];
        $thumbnails = [];
        $section = null;

        foreach (explode("\n", $rawTitles) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            $heading = $this->heading($line);
            if ($heading !== null) {
                $section = $heading;

                continue;
            }

            $value = $this->cleanItem($line);
            if ($value === '') {
                continue;
            }

            if ($section === 'thumbnails') {
                $thumbnails[] = $value;
            } else {
                $titles[] = $value;
            }
        }

        return [
            'titles' => array_slice($titles, 0, 6),
            'thumbnails' => array_slice($thumbnails, 0, 6),
        ];
    }

    private function heading(string $line): ?string
    {
        if (mb_strlen($line) > 40) {
            return null;
        }

        $normalized = mb_strtolower(trim($line, " \t.*_#-"));
        $normalized = preg_replace('/^\d+[\.\)]\s*/u', '', $normalized) ?? $normalized;
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú'],
            ['a', 'e', 'i', 'o', 'u'],
            $normalized
        );
        $normalized = preg_replace('/[^a-z\s]/', '', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        if (str_starts_with($normalized, 'titulo')) {
            return 'titles';
        }

        if (str_contains($normalized, 'thumbnail')) {
            return 'thumbnails';
        }

        return null;
    }

    private function cleanItem(string $value): string
    {
        $value = preg_replace('/^\s*(?:\d+[\.\)]|[-*•])\s*/u', '', $value) ?? $value;
        $value = preg_replace('/\*\*(.*?)\*\*/', '$1', $value) ?? $value;

        return trim($value, " \t.*");
    }
}
