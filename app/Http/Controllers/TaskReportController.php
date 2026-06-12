<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['anual', 'mensual', 'semanal', 'dia'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'week_start' => ['nullable', 'date'],
            'day' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $today = Carbon::today();
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);
        $scope = $validated['scope'];
        $orgId = $user->activeOrganizationId();

        [$title, $start, $end] = $this->reportService->resolveDateRange(
            $scope, $year, $month,
            $validated['week_start'] ?? null,
            $validated['day'] ?? null,
        );

        $tasks = $this->reportService->loadTasksForRange($orgId, $start, $end);
        $extraTasks = $this->reportService->loadExtraTasksForRange($orgId, $start, $end);
        $days = $this->reportService->buildDayGroups($tasks, $extraTasks, $start, $end);
        $company = $this->reportService->buildCompanyData($user);

        $filename = $this->reportService->generateAndSave(
            $scope, $start, $title, $days, $company, config('app.name')
        );

        $this->reportService->createHistory($orgId, $user, $scope, $filename, $validated);

        return Storage::disk('public')->download('reports/' . $filename, $filename);
    }
}
