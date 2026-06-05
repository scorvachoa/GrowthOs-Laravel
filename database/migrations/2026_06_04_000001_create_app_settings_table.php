<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('use_blocks')->default(true);
            $table->integer('block_hours')->default(2);
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            'use_blocks' => true,
            'block_hours' => 2,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
