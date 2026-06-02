<?php

namespace App\Http\Controllers;

use App\Models\ReportHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportHistoryController extends Controller
{
    public function index()
    {
        $histories = ReportHistory::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'user_name' => $item->user?->name ?? '-',
                'scope' => $item->scope,
                'filename' => $item->filename,
                'filters' => $item->filters_json,
                'created_at' => $item->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Reports/History', [
            'histories' => $histories,
        ]);
    }

    public function download(ReportHistory $reportHistory)
    {
        $filters = $reportHistory->filters_json ?? [];

        $query = http_build_query([
            'scope' => $filters['scope'] ?? $reportHistory->scope,
            'year' => $filters['year'] ?? '',
            'month' => $filters['month'] ?? '',
            'week_start' => $filters['week_start'] ?? '',
            'day' => $filters['day'] ?? '',
        ]);

        return redirect("/task-reports/pdf?{$query}");
    }
}
