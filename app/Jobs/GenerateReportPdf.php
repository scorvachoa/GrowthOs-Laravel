<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $filters,
        public User $user,
    ) {}

    public function handle(ReportService $reportService): void
    {
        $today = Carbon::today();
        $year = (int) ($this->filters['year'] ?? $today->year);
        $month = (int) ($this->filters['month'] ?? $today->month);
        $scope = $this->filters['scope'];
        $orgId = $this->user->activeOrganizationId();

        [$title, $start, $end] = $reportService->resolveDateRange(
            $scope, $year, $month,
            $this->filters['week_start'] ?? null,
            $this->filters['day'] ?? null,
        );

        $tasks = $reportService->loadTasksForRange($orgId, $start, $end);
        $extraTasks = $reportService->loadExtraTasksForRange($orgId, $start, $end);
        $days = $reportService->buildDayGroups($tasks, $extraTasks, $start, $end);
        $company = $reportService->buildCompanyData($this->user);

        $filename = $reportService->generateAndSave(
            $scope, $start, $title, $days, $company, config('app.name')
        );

        $reportService->createHistory($orgId, $this->user, $scope, $filename, $this->filters);
    }
}
