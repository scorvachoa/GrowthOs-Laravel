<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('logo_path', 500)->nullable();
            $table->string('primary_color', 20)->default('#4f46e5');
            $table->timestamps();
        });

        DB::table('organizations')->insert(['name' => 'GrowthOS', 'primary_color' => '#4f46e5']);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $defaultId = DB::table('organizations')->where('name', 'GrowthOS')->value('id');
        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $defaultId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::dropIfExists('organizations');
    }
};
