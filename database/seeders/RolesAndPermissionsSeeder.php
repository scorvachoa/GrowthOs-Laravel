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

            'view users',
            'create users',
            'edit users',
            'delete users',

            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            'view planning',
            'create planning',
            'edit planning',
            'delete planning',
            'export planning',

            'view tasks',

            'view ideas',
            'create ideas',
            'edit ideas',
            'delete ideas',
            'import ideas',
            'export ideas',

            'view reports',
            'download reports',
            'delete reports',

            'view youtube',

            'generate ai',
            'view ai history',
            'download ai',

            'view empresa',
            'create empresa',
            'edit empresa',
            'delete empresa',

            'view configuracion',
            'configure work hours',
            'configure youtube',
            'configure dashboard',
            'configure backup',

            'view vacations',
            'create vacations',
            'edit vacations',
            'delete vacations',

            'view time off',
            'create time off',
            'edit time off',
            'delete time off',
            'approve time off',
            'reject time off',

            'approve vacations',
            'reject vacations',

            'view backup',
            'create backup',
            'download backups',
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

        $orgIds = \DB::table('organizations')->pluck('id');
        $adminPermissions = array_values(array_filter($permissions, fn ($p) => !str_ends_with($p, 'roles')));

        foreach ($orgIds as $orgId) {
            $employee = Role::firstOrCreate([
                'name' => 'Employee',
                'organization_id' => $orgId,
            ]);

            $admin = Role::firstOrCreate([
                'name' => 'Admin',
                'organization_id' => $orgId,
            ]);

            if ($admin->permissions()->count() === 0) {
                $admin->givePermissionTo($adminPermissions);
            }
        }

        $oldPermissions = [
            'manage users',
            'manage tasks',
            'manage roles',
            'view ai',
            'view video tasks',
        ];

        foreach ($oldPermissions as $old) {
            if (Permission::where('name', $old)->exists()) {
                $superAdmin->revokePermissionTo($old);
                Permission::where('name', $old)->delete();
            }
        }
    }
}
