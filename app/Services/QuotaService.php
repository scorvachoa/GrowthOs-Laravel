<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\UsageEvent;

class QuotaService
{
    public function mode(): string
    {
        return config('quotas.mode', 'observe');
    }

    public function isEnforcing(): bool
    {
        return $this->mode() === 'enforce';
    }

    public function safetyCeiling(): int
    {
        return (int) config('quotas.safety_ceiling', 10000);
    }

    public function limitFor(string $event): ?int
    {
        $limit = config("quotas.limits.{$event}");

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Uso del evento en el mes calendario actual para la organización.
     */
    public function used(Organization|int $org, string $event): int
    {
        $orgId = $org instanceof Organization ? $org->id : (int) $org;

        return (int) UsageEvent::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('event', $event)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('quantity');
    }

    /**
     * Uso mensual agregado por evento para una organización (consola).
     *
     * @return array<string, int>
     */
    public function monthlyUsage(Organization|int $org, int $months = 1): array
    {
        $orgId = $org instanceof Organization ? $org->id : (int) $org;

        return UsageEvent::withoutGlobalScope('organization')
            ->selectRaw('event, SUM(quantity) as total')
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', now()->startOfMonth()->subMonthsNoOverflow($months - 1))
            ->groupBy('event')
            ->orderBy('event')
            ->pluck('total', 'event')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
