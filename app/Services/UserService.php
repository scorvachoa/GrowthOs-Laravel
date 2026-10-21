<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    protected function resolveRole(string $roleName): Role
    {
        $authUser = Auth::user();

        if ($roleName === RoleProvisioningService::ROLE_SUPER_ADMIN && ! $authUser->hasRole(RoleProvisioningService::ROLE_SUPER_ADMIN)) {
            throw ValidationException::withMessages(['role' => 'No puedes asignar el rol Super Admin']);
        }

        $organizationId = $authUser->activeOrganizationId();

        $role = Role::where('name', $roleName)
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            })
            ->first();

        if ($role) {
            return $role;
        }

        // Auto-sanación: empresas creadas antes del aprovisionamiento pueden no tener sus roles por defecto.
        if ($organizationId !== null && in_array($roleName, [RoleProvisioningService::ROLE_ADMIN, RoleProvisioningService::ROLE_EMPLOYEE], true)) {
            return app(RoleProvisioningService::class)->ensureRoleFor($organizationId, $roleName);
        }

        throw ValidationException::withMessages(['role' => "El rol \"{$roleName}\" no existe en tu empresa"]);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'organization_id' => Auth::user()->activeOrganizationId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['role'])) {
            $user->assignRole($this->resolveRole($data['role']));
        }

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->update([
                'password' => Hash::make($data['password']),
            ]);
        }

        if (! empty($data['role'])) {
            $user->syncRoles([$this->resolveRole($data['role'])]);
            // El middleware de Inertia cachea roles/permisos 5 min por usuario.
            Cache::forget("inertia:user:{$user->id}:roles");
            Cache::forget("inertia:user:{$user->id}:permissions");
        }

        return $user;
    }
}
