<?php

namespace App\Services\AI;

class CopyParser
{
    private const LABELS = [
        'title' => ['titulo', 'título', 'titulo corto', 'título corto'],
        'description' => ['descripcion', 'descripción', 'descripcion optimizada', 'descripción optimizada'],
        'cta' => ['cta', 'cta final'],
        'hashtags' => ['hashtags', 'hashtag'],
        'tags' => ['tags seo', 'tags_seo', 'tags', 'seo tags'],
    ];

    public function parse(string $rawCopy): array
    {
        $fields = $this->parseLabeledBlocks($rawCopy);
        $lines = array_filter(array_map('trim', explode("\n", $rawCopy)), fn($l) => $l !== '');

        if (empty($fields['title']) && !empty($lines)) {
            $fields['title'] = $this->cleanValue(preg_replace('/^\s*(?:\d+\.\s*)?[^:\n]{0,35}:\s*/', '', $lines[0]));
        }

        if (empty($fields['hashtags'])) {
            preg_match_all('/#[\wáéíóúñÁÉÍÓÚÑ]+/u', $rawCopy, $matches);
            $fields['hashtags'] = implode(' ', $matches[0] ?? []);
        }

        if (empty($fields['tags'])) {
            $tagLines = array_values(array_filter($lines, fn($l) => str_contains($l, ',') && !str_starts_with($l, '#')));
            $fields['tags'] = !empty($tagLines) ? end($tagLines) : '';
        }

        if (empty($fields['description'])) {
            $nonTagLines = array_values(array_filter($lines, function ($l) {
                return !str_starts_with($l, '#')
                    && !str_contains(mb_strtolower($l), 'tags seo')
                    && !preg_match('/^[#\wáéíóúñÁÉÍÓÚÑ\s]+$/u', $l);
            }));
            if (count($nonTagLines) > 1) {
                $fields['description'] = $this->cleanValue(implode("\n", array_slice($nonTagLines, 1)));
            }
        }

        return [
            'title' => $this->cleanValue($fields['title']),
            'description' => $this->cleanValue($fields['description']),
            'cta' => $this->cleanValue($fields['cta']),
            'hashtags' => $this->cleanValue($fields['hashtags']),
            'tags' => $this->cleanValue($fields['tags']),
            'raw' => trim($rawCopy),
        ];
    }

    private function parseLabeledBlocks(string $rawCopy): array
    {
        $fields = ['title' => '', 'description' => '', 'cta' => '', 'hashtags' => '', 'tags' => ''];
        $currentKey = null;
        $buffer = [];
        $labelPattern = '/^\s*(?:\d+\.\s*)?(?:\*\*)?([A-Za-zÁÉÍÓÚáéíóúÑñ_\s]{2,35})(?:\*\*)?\s*:\s*(.*)$/u';

        foreach (explode("\n", $rawCopy) as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($currentKey !== null && !empty($buffer) && end($buffer) !== '') {
                    $buffer[] = '';
                }
                continue;
            }

            if (preg_match($labelPattern, $line, $match)) {
                $key = $this->labelKey($match[1]);
                if ($key !== null) {
                    $this->flush($fields, $currentKey, $buffer);
                    $currentKey = $key;
                    $firstValue = trim($match[2]);
                    $buffer = $firstValue !== '' ? [$firstValue] : [];
                    continue;
                }
            }

            $heading = preg_replace('/^\s*\d+\.\s*/', '', trim($line, '* '));
            $key = $this->labelKey($heading);
            if ($key !== null) {
                $this->flush($fields, $currentKey, $buffer);
                $currentKey = $key;
                $buffer = [];
                continue;
            }

            if ($currentKey !== null) {
                $buffer[] = $line;
            }
        }

        $this->flush($fields, $currentKey, $buffer);
        return $fields;
    }

    private function flush(array &$fields, ?string &$currentKey, array &$buffer): void
    {
        if ($currentKey !== null) {
            $fields[$currentKey] = $this->cleanValue(implode("\n", $buffer));
        }
        $buffer = [];
        $currentKey = null;
    }

    private function labelKey(string $label): ?string
    {
        $normalized = $this->normalizeLabel($label);
        foreach (self::LABELS as $key => $aliases) {
            $normalizedAliases = array_map(fn($a) => $this->normalizeLabel($a), $aliases);
            if (in_array($normalized, $normalizedAliases, true)) {
                return $key;
            }
        }
        return null;
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
            $value
        );
        $value = preg_replace('/[^a-z_\s]/', '', $value);
        return preg_replace('/\s+/', ' ', trim($value));
    }

    private function cleanValue(string $value): string
    {
        $value = preg_replace('/\*\*(.*?)\*\*/', '$1', $value);
        $value = preg_replace('/^\s*[-*•]\s*/m', '', $value);
        return trim($value);
    }
}
