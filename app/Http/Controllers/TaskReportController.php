<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;


class TaskReportController extends Controller
{
    public function index()
    {
        return Inertia::render(
            'Reports/Tasks',
            [
                'stats' => [
                    'total' => 120,
                    'completed' => 85,
                    'pending' => 25,
                    'overdue' => 10,
                ],
            ]
        );
    }
}
