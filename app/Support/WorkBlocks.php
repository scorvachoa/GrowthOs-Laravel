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

    public static function all(): array
    {
        return self::ALL;
    }

    public static function generate(int $blockHours, ?int $startHour = null, ?int $endHour = null, ?int $lunchStart = null, ?int $lunchEnd = null): array
    {
        $startHour ??= 9;
        $endHour ??= 18;
        $lunchStart ??= 13;
        $lunchEnd ??= 14;

        $blocks = [];

        for ($h = $startHour; $h + $blockHours <= $lunchStart && $h + $blockHours <= $endHour; $h += $blockHours) {
            $blocks[] = sprintf('%02d:00-%02d:00', $h, $h + $blockHours);
        }

        for ($h = max($startHour, $lunchEnd); $h + $blockHours <= $endHour; $h += $blockHours) {
            $blocks[] = sprintf('%02d:00-%02d:00', $h, $h + $blockHours);
        }

        return $blocks;
    }

    public static function fromSettings(array $settings): array
    {
        $blockHours = $settings['block_hours'] ?? 2;
        $startHour = self::parseHour($settings['default_work_start'] ?? '09:00');
        $endHour = self::parseHour($settings['default_work_end'] ?? '18:00');
        $lunchStart = self::parseHour($settings['lunch_start'] ?? '13:00');
        $lunchEnd = self::parseHour($settings['lunch_end'] ?? '14:00');

        return self::generate($blockHours, $startHour, $endHour, $lunchStart, $lunchEnd);
    }

    public static function parseHour(string $time): int
    {
        return (int) explode(':', $time)[0];
    }

    public static function emptyCounts(?array $blocks = null): array
    {
        return array_fill_keys($blocks ?? self::ALL, 0);
    }

    public static function isValid(string $block, ?array $validBlocks = null): bool
    {
        return in_array($block, $validBlocks ?? self::ALL, true);
    }
}
