<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request, QuotaService $quota): Response
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $query = Organization::query()->withCount('users');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status !== '' && array_key_exists($status, (new Organization)->statusLabels())) {
            $query->where('status', $status);
        }

        $organizations = $query->orderBy('name')->get()->map(function (Organization $org) use ($quota) {
            $effective = $org->effectiveStatus();

            return [
                'id' => $org->id,
                'name' => $org->name,
                'status' => $effective,
                'status_label' => $org->statusLabels()[$effective] ?? $org->status,
                'plan' => $org->plan,
                'trial_ends_at' => $org->trial_ends_at?->toDateString(),
                'users_count' => $org->users_count,
                'usage_this_month' => $quota->monthlyUsage($org),
                'created_at' => $org->created_at?->toDateString(),
            ];
        });

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
            'statusOptions' => collect((new Organization)->statusLabels())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function suspend(Organization $organization)
    {
        $organization->update(['status' => Organization::STATUS_SUSPENDED]);
        $this->logAdminAction($organization, 'admin.organization.suspended');

        return back()->with('success', "Empresa \"{$organization->name}\" suspendida.");
    }

    public function activate(Organization $organization)
    {
        $organization->update(['status' => Organization::STATUS_ACTIVE]);
        $this->logAdminAction($organization, 'admin.organization.activated');

        return back()->with('success', "Empresa \"{$organization->name}\" reactivada.");
    }

    public function setPlan(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'plan' => ['nullable', 'string', 'max:30'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $organization->update([
            'plan' => $data['plan'] ?? null,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
        ]);

        $this->logAdminAction($organization, 'admin.organization.plan_updated', [
            'plan' => $organization->plan,
            'trial_ends_at' => $organization->trial_ends_at?->toDateString(),
        ]);

        return back()->with('success', "Plan de \"{$organization->name}\" actualizado.");
    }

    private function logAdminAction(Organization $organization, string $description, array $properties = []): void
    {
        $activity = activity('admin')
            ->performedOn($organization)
            ->causedBy(request()->user())
            ->withProperties($properties)
            ->log($description);

        $activity?->forceFill(['organization_id' => $organization->id])->save();
    }
}
