<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_sharings', function (Blueprint $table) {
            $table->id();
            $table->morphs('shareable');
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shareable_type', 'shareable_id', 'shared_with_user_id'], 'task_sharing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_sharings');
    }
};
