<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->boolean('used_in_planner')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->dropColumn('used_in_planner');
        });
    }
};
