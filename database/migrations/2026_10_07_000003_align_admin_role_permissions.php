<?php

use App\Services\RoleProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * El rol Admin vivo en producción heredó los 51 permisos del sistema
     * (incluía la gestión de roles). Se alinea con el catálogo: todo salvo
     * gestión de roles (47 permisos). Idempotente y por organización.
     */
    public function up(): void
    {
        $adminRoles = Role::where('name', RoleProvisioningService::ROLE_ADMIN)
            ->whereNotNull('organization_id')
            ->get();

        if ($adminRoles->isEmpty()) {
            return;
        }

        $service = app(RoleProvisioningService::class);
        $service->syncPermissions();

        $adminRoles->each(fn (Role $role) => $role->syncPermissions($service->adminPermissions()));
    }

    public function down(): void
    {
        // Alineación de datos sin registro del estado previo por organización.
    }
};
