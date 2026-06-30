<?php

namespace App\Enums;

enum VideoTaskStatus: string
{
    case Pending = 'pending';
    case ScriptReady = 'script_ready';
    case Editing = 'editing';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::ScriptReady => 'Guion listo',
            self::Editing => 'Edición',
            self::Review => 'Revisión',
            self::Scheduled => 'Programado',
            self::Published => 'Publicado',
            self::Cancelled => 'Cancelado',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function labels(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::values(), true);
    }
}
