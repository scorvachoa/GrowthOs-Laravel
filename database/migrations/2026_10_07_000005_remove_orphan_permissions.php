<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permisos huérfanos que nunca formaron parte del catálogo de
     * RoleProvisioningService ni se usan en rutas/policies: se eliminan
     * (los pivots de roles/usuarios se limpian por cascadeOnDelete).
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'edit configuracion',
                'view tasks',
            ])
            ->delete();
    }

    public function down(): void
    {
        foreach (['edit configuracion', 'view tasks'] as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
