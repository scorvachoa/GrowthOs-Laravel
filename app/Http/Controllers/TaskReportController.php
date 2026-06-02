<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\VideoTask;
use App\Models\ExtraTask;
use App\Support\VideoTaskStatuses;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskReportController extends Controller
{
    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['anual', 'mensual', 'semanal', 'dia'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'week_start' => ['nullable', 'date'],
            'day' => ['nullable', 'date'],
        ]);

        $today = Carbon::today();
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);
        $scope = $validated['scope'];

        [$title, $start, $end] = $this->resolveRange($scope, $year, $month, $validated['week_start'] ?? null, $validated['day'] ?? null);

        $tasks = VideoTask::query()
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();

        $extraTasks = ExtraTask::query()
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

            $days[] = [
                'date' => $dateStr,
                'tasks' => $items,
            ];
            $current->addDay();
        }

        $generatedAt = now()->format('Y-m-d H:i');

        $org = Organization::query()->first();
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

        $filename = 'reporte_' . $scope . '_' . $start->format('Y-m-d') . '.pdf';

        ReportHistory::create([
            'user_id' => auth()->id(),
            'scope' => $scope,
            'filename' => $filename,
            'filters_json' => $validated,
        ]);

        return $pdf->download($filename);
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
