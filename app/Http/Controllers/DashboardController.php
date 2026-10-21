<?php

namespace App\Http\Controllers;

use App\Services\AI\AIContentService;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected AIContentService $ai,
    ) {}

    public function index()
    {
        $settings = Auth::user()->merged_settings;
        $scope = $settings['dashboard_default_view'] ?? 'week';

        return Inertia::render(
            'Dashboard/Index',
            [
                'stats' => $this->dashboardService->stats($scope, Auth::user()),
            ]
        );
    }

    public function weeklySummary(Request $request)
    {
        $user = Auth::user();
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $cacheKey = "weekly_summary:v2:{$user->activeOrganizationId()}:{$weekStart}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $context = $this->dashboardService->weeklySummaryContext($user);
            $summary = $this->ai->generateWeeklySummary($context);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo generar el resumen semanal con IA. Inténtalo de nuevo.'], 502);
        }

        $payload = [
            'summary' => trim($summary),
            'generated_at' => now()->toIso8601String(),
            'period' => $context['periodo'],
        ];

        Cache::put($cacheKey, $payload, Carbon::now()->endOfWeek(Carbon::SUNDAY));

        return response()->json($payload, 201);
    }
}
