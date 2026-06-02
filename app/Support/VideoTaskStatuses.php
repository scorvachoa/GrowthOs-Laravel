<?php

namespace App\Support;

class VideoTaskStatuses
{
    public const ALL = [
        'pending',
        'script_ready',
        'editing',
        'review',
        'scheduled',
        'published',
        'cancelled',
    ];

    public static function labels(): array
    {
        return [
            'pending' => 'Pendiente',
            'script_ready' => 'Guion listo',
            'editing' => 'Edicion',
            'review' => 'Revision',
            'scheduled' => 'Programado',
            'published' => 'Publicado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }
}
