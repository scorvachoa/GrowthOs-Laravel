<?php

use App\Services\RoleProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * El módulo de backups se eliminó del sistema: retira sus 4 permisos
     * (los pivots de roles/usuarios se limpian por cascadeOnDelete) y
     * realinea el rol Admin con el catálogo vigente (sin permisos de backup).
     */
    public function up(): void
    {
        DB::table('permissions')
            ->whereIn('name', [
                'view backup',
                'create backup',
                'download backups',
                'configure backup',
            ])
            ->delete();

        $service = app(RoleProvisioningService::class);

        Role::where('name', RoleProvisioningService::ROLE_ADMIN)
            ->whereNotNull('organization_id')
            ->get()
            ->each(fn (Role $role) => $role->syncPermissions($service->adminPermissions()));
    }

    public function down(): void
    {
        $permissions = [
            'view backup',
            'create backup',
            'download backups',
            'configure backup',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
