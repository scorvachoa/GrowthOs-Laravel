<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use App\Services\UsageTrackingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckQuota
{
    public function handle(Request $request, Closure $next, string $event): Response
    {
        $user = $request->user();
        $orgId = $user?->activeOrganizationId();

        if (! $orgId) {
            return $next($request);
        }

        $quota = app(QuotaService::class);

        $used = $quota->used($orgId, $event);
        $ceiling = $quota->safetyCeiling();

        // Techo duro de seguridad: siempre se aplica, en cualquier modo.
        if ($used + 1 > $ceiling) {
            return $this->reject($request, $event, $used, $ceiling, 'límite técnico de seguridad');
        }

        $limit = $quota->limitFor($event);

        if ($limit !== null && $used + 1 > $limit) {
            if ($quota->isEnforcing()) {
                return $this->reject($request, $event, $used, $limit, 'límite de tu plan');
            }

            Log::info('quota: límite superado (modo observe, permitido)', [
                'organization_id' => $orgId,
                'event' => $event,
                'used' => $used,
                'limit' => $limit,
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            app(UsageTrackingService::class)->record($event);
        }

        return $response;
    }

    private function reject(Request $request, string $event, int $used, int $limit, string $reason): Response
    {
        $message = "Se alcanzó el {$reason} de uso ({$event}: {$used}/{$limit}). Contacta al administrador.";

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 429);
        }

        return redirect()->back()->with('error', $message);
    }
}
