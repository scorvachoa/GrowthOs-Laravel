<?php

namespace App\Services;

use App\Models\VideoTask;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PlanningValidator
{
    public function assertWorkingDay(string $date, array $workingDays): void
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso % 7;
        if (!in_array($dayOfWeek, $workingDays)) {
            throw ValidationException::withMessages([
                'task_date' => 'La fecha seleccionada no es un dia laborable.',
            ]);
        }
    }

    public function assertSlotAvailable(string $date, string $block, ?int $exceptId = null): void
    {
        $exists = VideoTask::query()
            ->where('task_date', '>=', $date)
            ->where('task_date', '<', Carbon::parse($date)->addDay())
            ->where('time_range', $block)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'time_range' => 'Bloque ocupado en la fecha seleccionada.',
            ]);
        }
    }

    public function resolveBlock(array $settings, string $block): string
    {
        $valid = WorkBlocks::fromSettings($settings);
        return in_array($block, $valid, true)
            ? $block
            : ($valid[0] ?? WorkBlocks::ALL[0]);
    }
}
