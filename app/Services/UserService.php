<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    protected function resolveRole(string $roleName): ?Role
    {
        $authUser = Auth::user();

        if ($roleName === 'Super Admin' && !$authUser->hasRole('Super Admin')) {
            throw new \Exception('No puedes asignar el rol Super Admin');
        }

        return Role::where('name', $roleName)
            ->where(function ($q) use ($authUser) {
                $q->where('organization_id', $authUser->activeOrganizationId())
                  ->orWhereNull('organization_id');
            })
            ->first();
    }

    public function create(array $data): User
    {
        $user = User::create([
            'organization_id' => Auth::user()->activeOrganizationId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (!empty($data['role'])) {
            $role = $this->resolveRole($data['role']);
            if ($role) {
                $user->assignRole($role);
            }
        }

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (!empty($data['password'])) {
            $user->update([
                'password' => Hash::make($data['password']),
            ]);
        }

        if (!empty($data['role'])) {
            $role = $this->resolveRole($data['role']);
            if ($role) {
                $user->syncRoles([$role]);
            }
        }

        return $user;
    }
}