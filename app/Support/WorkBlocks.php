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

    public static function emptyCounts(): array
    {
        return array_fill_keys(self::ALL, 0);
    }

    public static function isValid(string $block): bool
    {
        return in_array($block, self::ALL, true);
    }
}
