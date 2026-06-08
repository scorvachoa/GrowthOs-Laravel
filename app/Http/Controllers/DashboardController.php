<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
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
}