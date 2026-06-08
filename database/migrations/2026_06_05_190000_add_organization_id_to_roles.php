<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('guard_name')->constrained()->cascadeOnDelete();
        });

        // Assign existing roles to the first org (GrowthOS), except Super Admin
        $defaultOrgId = DB::table('organizations')->value('id');
        if ($defaultOrgId) {
            DB::table('roles')
                ->whereNull('organization_id')
                ->where('name', '!=', 'Super Admin')
                ->update(['organization_id' => $defaultOrgId]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
