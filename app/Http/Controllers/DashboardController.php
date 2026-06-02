<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index()
    {
        return Inertia::render(
            'Dashboard/Index',
            [
                'stats' => $this->dashboardService->stats(),
            ]
        );
    }
}