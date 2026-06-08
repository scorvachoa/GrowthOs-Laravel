<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->string('status', 24)->default('processing')->after('organization_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
