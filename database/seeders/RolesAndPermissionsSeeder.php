<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'manage users',
            'manage roles',
            'manage tasks',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
        ]);

        $superAdmin->givePermissionTo($permissions);

        $employee = Role::firstOrCreate([
            'name' => 'Employee',
        ]);
    }
}