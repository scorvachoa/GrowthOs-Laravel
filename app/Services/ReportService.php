<?php

namespace App\Services;

use App\Models\DayObservation;
use App\Models\ExtraTask;
use App\Models\Organization;
use App\Models\ReportHistory;
use App\Models\User;
use App\Models\VideoTask;
use App\Enums\VideoTaskStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReportService
{
    public function resolveDateRange(string $scope, int $year, int $month, ?string $weekStart, ?string $day): array
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

    public function loadTasksForRange(?int $orgId, Carbon $start, Carbon $end): Collection
    {
        return VideoTask::query()
            ->with('channel', 'sessions')
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('task_date', [$start, $end])
                  ->orWhereHas('sessions', function ($sq) use ($start, $end) {
                      $sq->whereBetween('date', [$start, $end]);
                  });
            })
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();
    }

    public function loadExtraTasksForRange(?int $orgId, Carbon $start, Carbon $end): Collection
    {
        return ExtraTask::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->orderBy('task_date')
            ->orderBy('time_range')
            ->get();
    }

    public function buildDayGroups(?int $orgId, Collection $tasks, Collection $extraTasks, Carbon $start, Carbon $end): array
    {
        $labels = VideoTaskStatus::labels();
        $observations = DayObservation::query()
            ->where('organization_id', $orgId)
            ->where('task_date', '>=', $start)
            ->where('task_date', '<', $end)
            ->get()
            ->keyBy(fn ($o) => $o->task_date->format('Y-m-d'));

        $sessionsByDate = [];
        foreach ($tasks as $task) {
            foreach ($task->sessions as $session) {
                $sessionKey = $session->date->format('Y-m-d');
                if ($sessionKey === $task->task_date->format('Y-m-d')) continue;
                if (!isset($sessionsByDate[$sessionKey])) {
                    $sessionsByDate[$sessionKey] = [];
                }
                $sessionsByDate[$sessionKey][] = [
                    'time_range' => $session->time_range,
                    'title' => $task->title,
                    'status_label' => $session->status === 'completed' ? 'Completado' : 'En progreso',
                    'youtube_url' => $task->youtube_url,
                    'type' => 'session',
                    'channel_name' => $task->channel?->name,
                ];
            }
        }

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
                    'channel_name' => $task->channel?->name,
                ];
            }
            foreach (($sessionsByDate[$dateStr] ?? []) as $session) {
                $items[] = $session;
            }
            foreach ($dayExtras as $task) {
                $items[] = [
                    'time_range' => $task->time_range,
                    'title' => $task->title,
                    'status_label' => $task->status,
                    'youtube_url' => null,
                    'type' => 'extra',
                ];
            }
            $typeOrder = ['video' => 0, 'session' => 1, 'extra' => 2];
            usort($items, fn ($a, $b) =>
                strcmp(explode('-', $a['time_range'])[0] ?? '', explode('-', $b['time_range'])[0] ?? '')
                ?: ($typeOrder[$a['type']] <=> $typeOrder[$b['type']])
            );

            $days[] = [
                'date' => $dateStr,
                'tasks' => $items,
                'observation' => isset($observations[$dateStr]) ? $observations[$dateStr]->notes : null,
            ];
            $current->addDay();
        }

        return $days;
    }

    public function buildCompanyData(User $user): array
    {
        $orgId = $user->activeOrganizationId();
        $org = $orgId ? Organization::find($orgId) : ($user->organization ?? Organization::query()->first());

        return $org ? [
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
    }

    public function generateAndSave(string $scope, Carbon $start, string $title, array $days, array $company, string $systemName): string
    {
        $generatedAt = now()->format('Y-m-d H:i');

        $pdf = Pdf::loadView('pdf.report', compact('title', 'days', 'generatedAt', 'company', 'systemName'));
        $pdf->setPaper('letter');

        $filename = 'reporte_' . $scope . '_' . $start->format('Y-m-d') . '_' . now()->timestamp . '.pdf';
        Storage::disk('public')->put('reports/' . $filename, $pdf->output());

        return $filename;
    }

    public function createHistory(?int $orgId, User $user, string $scope, string $filename, array $filters): void
    {
        ReportHistory::create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'scope' => $scope,
            'filename' => $filename,
            'filters_json' => $filters,
        ]);
    }
}
