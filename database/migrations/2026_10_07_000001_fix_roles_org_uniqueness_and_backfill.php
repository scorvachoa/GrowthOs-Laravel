<?php

use App\Services\RoleProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los roles deben ser únicos POR EMPRESA, no globalmente: la validación de
     * RoleController y el modelo multi-empresa asumen (organization_id, name).
     * El unique global (name, guard_name) provocaba un error 500 al crear un
     * rol con un nombre ya usado en otra empresa.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['organization_id', 'name', 'guard_name'], 'roles_org_name_guard_unique');
        });

        $provisioning = app(RoleProvisioningService::class);

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            $provisioning->ensureDefaultsFor((int) $organizationId);
        }
    }

    public function down(): void
    {
        // El unique global solo puede restaurarse si dos empresas no comparten nombre de rol.
        $duplicated = DB::table('roles')
            ->select('name', 'guard_name')
            ->groupBy('name', 'guard_name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicated) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_org_name_guard_unique');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
