<?php

namespace App\Http\Controllers;

use App\Services\PlanningCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanningController extends Controller
{
    public function __construct(
        private PlanningCalendarService $calendar,
    ) {}

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
            'calendar' => $this->calendar->snapshot($year, $month, $weekStart),
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
            $this->calendar->snapshot($year, $month, $weekStart)
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
}
