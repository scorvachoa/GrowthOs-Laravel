<?php

namespace App\Support;

class WorkBlocks
{
    public const ALL = [
        '09:00-11:00',
        '11:00-13:00',
        '14:00-16:00',
        '16:00-18:00',
    ];

    public const MORNING_START = 9;
    public const MORNING_END = 13;
    public const AFTERNOON_START = 14;
    public const AFTERNOON_END = 18;

    public static function all(): array
    {
        return self::ALL;
    }

    public static function generate(int $blockHours, ?int $startHour = null, ?int $endHour = null): array
    {
        $startHour ??= self::MORNING_START;
        $endHour ??= self::AFTERNOON_END;

        $blocks = [];

        for ($h = $startHour; $h + $blockHours <= min($endHour, self::MORNING_END); $h += $blockHours) {
            $blocks[] = sprintf('%02d:00-%02d:00', $h, $h + $blockHours);
        }

        for ($h = max($startHour, self::AFTERNOON_START); $h + $blockHours <= $endHour; $h += $blockHours) {
            $blocks[] = sprintf('%02d:00-%02d:00', $h, $h + $blockHours);
        }

        return $blocks;
    }

    public static function fromSettings(array $settings): array
    {
        $blockHours = $settings['block_hours'] ?? 2;
        $startHour = self::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = self::parseHour($settings['default_work_end'] ?? '18:00');

        return self::generate($blockHours, $startHour, $endHour);
    }

    public static function parseHour(string $time): int
    {
        return (int) explode(':', $time)[0];
    }

    public static function emptyCounts(?array $blocks = null): array
    {
        return array_fill_keys($blocks ?? self::ALL, 0);
    }

    public static function isValid(string $block): bool
    {
        return in_array($block, self::ALL, true);
    }
}
