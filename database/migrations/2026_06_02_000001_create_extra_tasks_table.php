<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_tasks', function (Blueprint $table) {
            $table->id();
            $table->date('task_date');
            $table->string('time_range');
            $table->string('title');
            $table->string('status', 24)->default('pending');
            $table->string('location', 20)->default('oficina');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['task_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_tasks');
    }
};
