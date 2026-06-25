<?php

namespace App\Http\Controllers;

use App\Models\DayObservation;
use App\Models\VideoTask;
use App\Models\WorkSession;
use App\Services\PlanningCalendarService;
use App\Support\WorkBlocks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlanningController extends Controller
{
    public function __construct(
        private PlanningCalendarService $calendar,
    ) {}

    private function userWorkBlocks(): array
    {
        $settings = Auth::user()->merged_settings;
        return WorkBlocks::fromSettings($settings);
    }

    public function index(Request $request)
    {
        $today = Carbon::today();
        $year = (int) $request->integer('year', $today->year);
        $month = (int) $request->integer('month', $today->month);

        if ($month < 1 || $month > 12) {
            $month = $today->month;
        }

        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }

        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        if ($request->filled('week_start')) {
            try {
                $weekStart = Carbon::parse($request->string('week_start'))
                    ->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                // keep default
            }
        }

        $view = $request->string('view')->toString();
        if (! in_array($view, ['month', 'week'], true)) {
            $view = '';
        }

        return Inertia::render('Planning/Index', [
            'calendar' => $this->calendar->snapshot($year, $month, $weekStart, $this->userWorkBlocks()),
            'initial_view' => $view,
        ]);
    }

    public function snapshot(Request $request)
    {
        $year = (int) $request->integer('year');
        $month = (int) $request->integer('month');

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Periodo invalido'], 400);
        }

        $weekStart = null;
        if ($request->filled('week_start')) {
            try {
                $weekStart = Carbon::parse($request->string('week_start'))
                    ->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                return response()->json(['message' => 'Semana invalida'], 400);
            }
        }

        return response()->json(
            $this->calendar->snapshot($year, $month, $weekStart, $this->userWorkBlocks())
        );
    }

    public function tasksByDate(Request $request)
    {
        $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        return response()->json(
            $this->calendar->tasksForDate($request->string('fecha'))
        );
    }

    public function getObservation(Request $request)
    {
        $request->validate(['fecha' => ['required', 'date']]);

        $obs = DayObservation::query()
            ->where('organization_id', Auth::user()->activeOrganizationId())
            ->where('task_date', $request->string('fecha'))
            ->first();

        return response()->json([
            'notes' => $obs?->notes ?? '',
        ]);
    }

    public function saveObservation(Request $request)
    {
        $request->validate([
            'fecha' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $orgId = Auth::user()->activeOrganizationId();
        $date = $request->string('fecha');
        $notes = $request->input('notes');

        if ($notes === null || trim($notes) === '') {
            DayObservation::where('organization_id', $orgId)
                ->where('task_date', $date)
                ->delete();
        } else {
            DayObservation::updateOrCreate(
                ['organization_id' => $orgId, 'task_date' => $date],
                ['notes' => $notes, 'created_by' => Auth::id()],
            );
        }

        PlanningCalendarService::bustCache();

        return response()->json(['ok' => true]);
    }

    public function occupiedBlocks(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
            'except_task_id' => ['nullable', 'exists:video_tasks,id'],
        ]);

        $date = $request->string('date');
        $query = VideoTask::query()
            ->where('task_date', '>=', $date)
            ->where('task_date', '<', Carbon::parse($date)->addDay());

        if ($request->filled('except_task_id')) {
            $query->where('id', '!=', $request->integer('except_task_id'));
        }

        $occupied = $query->pluck('time_range')->toArray();

        $sessionOccupied = WorkSession::where('date', $date)
            ->whereNotNull('time_range')
            ->where('status', 'in_progress')
            ->when($request->filled('except_task_id'), fn ($q) => $q->where('video_task_id', '!=', $request->integer('except_task_id')))
            ->pluck('time_range')
            ->toArray();

        $occupied = array_unique(array_merge($occupied, $sessionOccupied));

        $allBlocks = $this->userWorkBlocks();

        return response()->json([
            'occupied' => $occupied,
            'available' => array_values(array_diff($allBlocks, $occupied)),
            'all' => $allBlocks,
        ]);
    }
}
