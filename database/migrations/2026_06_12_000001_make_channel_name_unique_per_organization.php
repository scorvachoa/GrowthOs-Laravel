<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
