<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('admin_invite_code', 60)->nullable()->unique()->after('primary_color');
            $table->string('invite_code', 60)->nullable()->unique()->after('admin_invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['admin_invite_code', 'invite_code']);
        });
    }
};
