<?php

namespace App\Http\Controllers;

use App\Models\TimeOff;
use App\Services\PlanningCalendarService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TimeOffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $canManage = $isSuperAdmin || $user->can('approve time off') || $user->can('edit planning');

        $timeOffs = TimeOff::query()
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
            ->map(fn ($t) => [
                'id' => $t->id,
                'user_name' => $t->user?->name,
                'date' => $t->date->format('Y-m-d'),
                'start_time' => $t->start_time,
                'end_time' => $t->end_time,
                'type' => $t->type,
                'reason' => $t->reason,
                'status' => $t->status,
                'approved_by' => $t->approver?->name,
                'approved_at' => $t->approved_at?->format('Y-m-d H:i'),
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('TimeOff/Index', [
            'timeOffs' => $timeOffs,
            'can_approve' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(['medico', 'personal', 'tramite', 'otro'])],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        TimeOff::create([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'status' => 'pendiente',
        ]);

        PlanningCalendarService::bustCache($request->user()->id);

        return redirect()->back()->with('success', 'Solicitud de permiso creada.');
    }

    public function approve(TimeOff $timeOff)
    {
        $user = request()->user();
        if (!$user->hasRole('Super Admin') && !$this->canManageOrg($user, $timeOff->user?->organization_id)) {
            abort(403);
        }

        $timeOff->update([
            'status' => 'aprobado',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        PlanningCalendarService::bustCache($timeOff->user_id);

        return redirect()->back()->with('warning', 'Permiso aprobado.');
    }

    public function reject(Request $request, TimeOff $timeOff)
    {
        $user = $request->user();
        if (!$user->hasRole('Super Admin') && !$this->canManageOrg($user, $timeOff->user?->organization_id)) {
            abort(403);
        }

        $timeOff->update(['status' => 'rechazado']);

        return redirect()->back()->with('error', 'Permiso rechazado.');
    }

    public function update(Request $request, TimeOff $timeOff)
    {
        $user = $request->user();
        if ($timeOff->user_id !== $user->id && !$user->hasRole('Super Admin') && !$this->canManageOrg($user, $timeOff->user?->organization_id)) {
            abort(403);
        }

        if ($timeOff->status === 'aprobado') {
            return back()->with('error', 'No puedes editar permisos aprobados.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(['medico', 'personal', 'tramite', 'otro'])],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $timeOff->update([
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
        ]);

        PlanningCalendarService::bustCache($timeOff->user_id);

        return redirect()->back()->with('success', 'Permiso actualizado.');
    }

    public function destroy(TimeOff $timeOff)
    {
        $user = request()->user();
        if ($timeOff->user_id !== $user->id && !$user->hasRole('Super Admin') && !$this->canManageOrg($user, $timeOff->user?->organization_id)) {
            abort(403);
        }

        if ($timeOff->status === 'aprobado') {
            return back()->with('error', 'No puedes eliminar permisos aprobados.');
        }

        $timeOff->delete();
        PlanningCalendarService::bustCache($timeOff->user_id);

        return redirect()->back()->with('error', 'Solicitud eliminada.');
    }

    private function canManageOrg($user, ?int $orgId): bool
    {
        return $orgId && $user->activeOrganizationId() === $orgId && ($user->can('approve time off') || $user->can('edit planning'));
    }
}
