<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('channels', 'locale')) {
            Schema::table('channels', function (Blueprint $table) {
                $table->string('locale', 5)->default('es')->after('color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('channels', 'locale')) {
            Schema::table('channels', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
