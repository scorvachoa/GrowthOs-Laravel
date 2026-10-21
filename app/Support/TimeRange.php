<?php

namespace App\Support;

class TimeRange
{
    public const INVALID_MESSAGE = 'La hora fin debe ser mayor a la hora de inicio';

    public static function endsAfterStart(string $timeRange): bool
    {
        if (! str_contains($timeRange, '-')) {
            return true;
        }

        [$start, $end] = explode('-', $timeRange, 2);

        if (strlen($start) !== 5 || strlen($end) !== 5) {
            return true;
        }

        return $end > $start;
    }
}
