<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vacation;
use App\Services\PlanningCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VacationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $canManage = $isSuperAdmin || $user->can('approve vacations') || $user->can('edit planning');

        $vacations = Vacation::query()
            ->with('user', 'approver')
            ->when(
                !$canManage,
                fn ($q) => $q->where('user_id', $user->id)
            )
            ->when(
                !$isSuperAdmin && $user->can('edit planning'),
                fn ($q) => $q->whereHas('user', fn ($q) => $q->where('organization_id', $user->activeOrganizationId()))
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'user_name' => $v->user?->name,
                'start_date' => $v->start_date->format('Y-m-d'),
                'end_date' => $v->end_date->format('Y-m-d'),
                'type' => $v->type,
                'days_used' => $v->days_used,
                'year' => $v->year,
                'status' => $v->status,
                'reason' => $v->reason,
                'approved_by' => $v->approver?->name,
                'approved_at' => $v->approved_at?->format('Y-m-d H:i'),
                'created_at' => $v->created_at->format('Y-m-d H:i'),
            ]);

        $usedDays = Vacation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pendiente', 'aprobado'])
            ->where('year', Carbon::today()->year)
            ->sum('days_used');

        return Inertia::render('Vacations/Index', [
            'vacations' => $vacations,
            'remaining_days' => max(0, 30 - $usedDays),
            'can_approve' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', Rule::in(['completa', 'parcial'])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $totalDays = $validated['type'] === 'completa' ? 30 : 15;
        $start = Carbon::parse($validated['start_date']);
        $end = $start->copy()->addDays($totalDays - 1);

        $usedThisYear = Vacation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pendiente', 'aprobado'])
            ->where('year', $start->year)
            ->sum('days_used');

        if ($usedThisYear + $totalDays > 30) {
            return back()->withErrors(['start_date' => 'Excede el limite de 30 dias anuales']);
        }

        Vacation::create([
            'user_id' => $user->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $validated['type'],
            'days_used' => $totalDays,
            'year' => $start->year,
            'reason' => $validated['reason'],
            'status' => 'pendiente',
        ]);

        PlanningCalendarService::bustCache($user->id);

        return redirect()->back()->with('success', 'Solicitud de vacaciones creada.');
    }

    public function approve(Vacation $vacation)
    {
        $user = request()->user();
        if (!$user->hasRole('Super Admin') && !$this->canManageOrg($user, $vacation->user?->organization_id)) {
            abort(403);
        }

        $vacation->update([
            'status' => 'aprobado',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        PlanningCalendarService::bustCache($vacation->user_id);

        return redirect()->back()->with('warning', 'Vacaciones aprobadas.');
    }

    public function reject(Request $request, Vacation $vacation)
    {
        $user = $request->user();
        if (!$user->hasRole('Super Admin') && !$this->canManageOrg($user, $vacation->user?->organization_id)) {
            abort(403);
        }

        $vacation->update(['status' => 'rechazado']);

        return redirect()->back()->with('error', 'Vacaciones rechazadas.');
    }

    public function update(Request $request, Vacation $vacation)
    {
        $user = $request->user();
        if ($vacation->user_id !== $user->id && !$user->hasRole('Super Admin') && !$this->canManageOrg($user, $vacation->user?->organization_id)) {
            abort(403);
        }

        if ($vacation->status === 'aprobado') {
            return back()->with('error', 'No puedes editar vacaciones aprobadas.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['completa', 'parcial'])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $totalDays = $validated['type'] === 'completa' ? 30 : 15;
        $start = Carbon::parse($validated['start_date']);
        $end = $start->copy()->addDays($totalDays - 1);

        $usedThisYear = Vacation::query()
            ->where('user_id', $vacation->user_id)
            ->where('id', '!=', $vacation->id)
            ->whereIn('status', ['pendiente', 'aprobado'])
            ->where('year', $start->year)
            ->sum('days_used');

        if ($usedThisYear + $totalDays > 30) {
            return back()->withErrors(['start_date' => 'Excede el limite de 30 dias anuales']);
        }

        $vacation->update([
            'type' => $validated['type'],
            'start_date' => $start,
            'end_date' => $end,
            'days_used' => $totalDays,
            'year' => $start->year,
            'reason' => $validated['reason'],
        ]);

        PlanningCalendarService::bustCache($vacation->user_id);

        return redirect()->back()->with('success', 'Vacaciones actualizadas.');
    }

    public function destroy(Vacation $vacation)
    {
        $user = request()->user();
        if ($vacation->user_id !== $user->id && !$user->hasRole('Super Admin') && !$this->canManageOrg($user, $vacation->user?->organization_id)) {
            abort(403);
        }

        if ($vacation->status === 'aprobado') {
            return back()->with('error', 'No puedes eliminar vacaciones aprobadas.');
        }

        $vacation->delete();
        PlanningCalendarService::bustCache($vacation->user_id);

        return redirect()->back()->with('error', 'Solicitud eliminada.');
    }

    private function canManageOrg($user, ?int $orgId): bool
    {
        return $orgId && $user->activeOrganizationId() === $orgId && ($user->can('approve vacations') || $user->can('edit planning'));
    }
}
