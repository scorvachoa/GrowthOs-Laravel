<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('primary_color')->index();
            $table->string('plan', 30)->nullable()->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('plan');
        });

        // La org "GrowthOS" se creó con DB::table()->insert (sin timestamps).
        DB::table('organizations')
            ->whereNull('created_at')
            ->update(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['status', 'plan', 'trial_ends_at']);
        });
    }
};
