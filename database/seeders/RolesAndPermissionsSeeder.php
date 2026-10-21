<?php

namespace Database\Seeders;

use App\Services\RoleProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $provisioning = app(RoleProvisioningService::class);

        $provisioning->syncPermissions();

        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            $provisioning->ensureDefaultsFor((int) $orgId);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => RoleProvisioningService::ROLE_SUPER_ADMIN,
        ]);

        $oldPermissions = [
            'manage users',
            'manage tasks',
            'manage roles',
            'view ai',
            'view video tasks',
            'view tasks',
            'edit configuracion',
            'view backup',
            'create backup',
            'download backups',
            'configure backup',
        ];

        foreach ($oldPermissions as $old) {
            if (Permission::where('name', $old)->exists()) {
                $superAdmin->revokePermissionTo($old);
                Permission::where('name', $old)->delete();
            }
        }
    }
}
