<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $roles = Role::query()
            ->withCount('permissions', 'users')
            ->when(
                !$user->hasRole('Super Admin'),
                fn ($query) => $query
                    ->where('organization_id', $user->activeOrganizationId())
                    ->where('name', '!=', 'Super Admin')
            )
            ->when(
                $user->hasRole('Super Admin'),
                fn ($query) => $query
                    ->where(function ($q) use ($user) {
                        $orgId = $user->activeOrganizationId();
                        $q->whereNull('organization_id'); // always include global roles (Super Admin)
                        if ($orgId) {
                            $q->orWhere('organization_id', $orgId);
                        }
                    })
            )
            ->when(
                $request->search,
                fn ($query, $search) =>
                    $query->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'organization_id' => $role->organization_id,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
            ]);

        $companies = \App\Models\Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
                'organization_id' => $request->organization_id,
            ],
            'companies' => $companies,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('roles', 'name')->where(fn ($q) =>
                    $q->where('organization_id', $request->user()->activeOrganizationId())
                ),
            ],
            'permissions' => ['array'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'organization_id' => $request->user()->activeOrganizationId(),
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Request $request, Role $role)
    {
        $user = $request->user();
        if (!$user->hasRole('Super Admin') && $role->organization_id !== $user->activeOrganizationId()) {
            abort(403);
        }

        $users = $role->users()->latest()->take(5)->get(['id', 'name', 'email']);

        $organization = $role->organization_id
            ? \App\Models\Organization::find($role->organization_id)?->only(['id', 'name'])
            : null;

        return Inertia::render('Roles/Edit', [
            'role' => array_merge($role->toArray(), [
                'users_count' => $role->users()->count(),
            ]),
            'permissions' => Permission::all(),
            'selectedPermissions' => $role->permissions->pluck('name'),
            'roleUsers' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
            'organization' => $organization,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $user = $request->user();
        if (!$user->hasRole('Super Admin') && $role->organization_id !== $user->activeOrganizationId()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('organization_id', $role->organization_id))
                    ->ignore($role->id),
            ],
            'permissions' => ['array'],
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('warning', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, Role $role)
    {
        $user = $request->user();
        if (!$user->hasRole('Super Admin') && $role->organization_id !== $user->activeOrganizationId()) {
            abort(403);
        }

        $role->delete();

        return redirect()
            ->back()
            ->with('error', 'Rol eliminado correctamente.');
    }
}
