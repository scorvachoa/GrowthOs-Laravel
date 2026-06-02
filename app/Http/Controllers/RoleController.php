<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()
            ->when(
                $request->search,
                fn ($query, $search) =>
                    $query->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
            )
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Roles/Index',
            [
                'roles' => $roles,

                'filters' => [
                    'search' => $request->search,
                ],
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'Roles/Create',
            [
                'permissions' =>
                    Permission::all(),
            ]
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'unique:roles,name',
            ],

            'permissions' => [
                'array',
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        $role->syncPermissions(
            $validated['permissions'] ?? []
        );

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'Role created successfully.'
            );
    }

    public function edit(Role $role)
    {
        return Inertia::render(
            'Roles/Edit',
            [
                'role' => $role,

                'permissions' =>
                    Permission::all(),

                'selectedPermissions' =>
                    $role->permissions
                        ->pluck('name'),
            ]
        );
    }

    public function update(
        Request $request,
        Role $role
    ) {

        $validated = $request->validate([
            'name' => [
                'required',
            ],

            'permissions' => [
                'array',
            ],
        ]);

        $role->update([
            'name' => $validated['name'],
        ]);

        $role->syncPermissions(
            $validated['permissions'] ?? []
        );

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'Role updated successfully.'
            );
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return redirect()
            ->back()
            ->with(
                'success',
                'Role deleted successfully.'
            );
    }
}