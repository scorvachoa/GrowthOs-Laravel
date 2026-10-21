<?php

namespace App\Services;

use App\Models\UsageEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UsageTrackingService
{
    /**
     * Un fallo de tracking nunca debe romper el flujo que lo originó.
     */
    public function record(string $event, int $quantity = 1, array $metadata = []): void
    {
        try {
            $user = Auth::user();
            $orgId = $user?->activeOrganizationId();

            if (! $orgId) {
                return;
            }

            UsageEvent::create([
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'event' => $event,
                'quantity' => $quantity,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('usage tracking failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
