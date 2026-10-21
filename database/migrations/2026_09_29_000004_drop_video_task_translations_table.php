<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('video_task_translations');
    }

    public function down(): void
    {
        Schema::create('video_task_translations', function ($table) {
            $table->id();
            $table->foreignId('video_task_id')->constrained('video_tasks')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('title')->nullable();
            $table->text('script')->nullable();
            $table->text('copy')->nullable();
            $table->text('youtube_url')->nullable();
            $table->timestamps();
            $table->unique(['video_task_id', 'locale']);
        });
    }
};
