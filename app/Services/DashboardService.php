<?php

namespace App\Services;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function stats(): array
    {
        return [
            'total_users' => User::count(),

            'new_users' => User::query()
                ->whereDate(
                    'created_at',
                    now()->today()
                )
                ->count(),

            'recent_users' => User::query()
                ->latest()
                ->take(5)
                ->get([
                    'id',
                    'name',
                    'email',
                    'created_at',
                ]),
            'recent_activity' => Activity::query()
                ->latest()
                ->take(10)
                ->get(),
        ];
    }
}