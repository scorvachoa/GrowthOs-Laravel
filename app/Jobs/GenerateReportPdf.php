<?php

namespace App\Jobs;

use App\Models\ExtraTask;
use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\User;
use App\Models\VideoTask;
use App\Support\VideoTaskStatuses;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateReportPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $filters,
        public User $user,
    ) {}

    public function handle(): void
    {
        $today = Carbon::today();
        $year = (int) ($this->filters['year'] ?? $today->year);
        $month = (int) ($this->filters['month'] ?? $today->month);
        $scope = $this->filters['scope'];

        [$title, $start, $end] = $this->resolveRange(
            $scope, $year, $month,
            $this->filters['week_start'] ?? null,
            $this->filters['day'] ?? null,
        );

        $orgId = $this->user->activeOrganizationId();

        $tasks = VideoTask::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $extraTasks = ExtraTask::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $labels = VideoTaskStatuses::labels();
        $days = [];
        $current = $start->copy();
        while ($current < $end) {
            $dateStr = $current->format('Y-m-d');
            $dayTasks = $tasks->filter(fn ($t) => $t->task_date->format('Y-m-d') === $dateStr);
            $dayExtras = $extraTasks->filter(fn ($t) => $t->task_date->format('Y-m-d') === $dateStr);

            $items = [];
            foreach ($dayTasks as $task) {
                $items[] = [
                    'time_range' => $task->time_range,
                    'title' => $task->title,
                    'status_label' => $labels[$task->status] ?? $task->status,
                    'youtube_url' => $task->youtube_url,
                    'type' => 'video',
                ];
            }
            foreach ($dayExtras as $task) {
                $items[] = [
                    'time_range' => $task->time_range,
                    'title' => $task->title . ' (Extra)',
                    'status_label' => $task->status,
                    'youtube_url' => null,
                    'type' => 'extra',
                ];
            }

            $days[] = ['date' => $dateStr, 'tasks' => $items];
            $current->addDay();
        }

        $generatedAt = now()->format('Y-m-d H:i');
        $org = $this->user->organization ?? Organization::query()->first();
        $company = $org ? [
            'name' => $org->name,
            'primary_color' => $org->primary_color ?: '#4f46e5',
            'logo_base64' => $org->logo_path && Storage::disk('public')->exists($org->logo_path)
                ? 'data:image/' . pathinfo($org->logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(Storage::disk('public')->get($org->logo_path))
                : null,
        ] : [
            'name' => 'GrowthOS',
            'primary_color' => '#4f46e5',
            'logo_base64' => null,
        ];

        $systemName = config('app.name');

        $pdf = Pdf::loadView('pdf.report', compact('title', 'days', 'generatedAt', 'company', 'systemName'));
        $pdf->setPaper('letter');

        $filename = 'reporte_' . $scope . '_' . $start->format('Y-m-d') . '_' . now()->timestamp . '.pdf';
        $filePath = 'reports/' . $filename;
        Storage::disk('public')->put($filePath, $pdf->output());

        ReportHistory::create([
            'organization_id' => $this->user->activeOrganizationId(),
            'user_id' => $this->user->id,
            'scope' => $scope,
            'filename' => $filename,
            'filters_json' => $this->filters,
        ]);
    }

    private function resolveRange(string $scope, int $year, int $month, ?string $weekStart, ?string $day): array
    {
        $today = Carbon::today();

        if ($scope === 'anual') {
            return [
                "Reporte anual {$year}",
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        if ($scope === 'mensual') {
            return [
                "Reporte mensual {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                Carbon::create($year, $month, 1)->startOfDay(),
                Carbon::create($year, $month, 1)->endOfMonth()->endOfDay(),
            ];
        }

        if ($scope === 'semanal') {
            $start = $weekStart ? Carbon::parse($weekStart)->startOfDay() : $today->copy()->startOfWeek(Carbon::MONDAY);
            return [
                "Reporte semanal {$start->format('Y-m-d')} a {$start->copy()->addDays(6)->format('Y-m-d')}",
                $start->copy(),
                $start->copy()->addDays(6)->endOfDay(),
            ];
        }

        if ($scope === 'dia') {
            $d = $day ? Carbon::parse($day) : $today->copy();
            return [
                "Reporte diario {$d->format('Y-m-d')}",
                $d->copy()->startOfDay(),
                $d->copy()->endOfDay(),
            ];
        }

        throw new \InvalidArgumentException('Scope invalido');
    }
}
