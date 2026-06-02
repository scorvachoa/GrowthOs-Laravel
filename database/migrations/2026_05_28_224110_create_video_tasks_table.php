<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_tasks', function (
            Blueprint $table
        ) {

            $table->id();

            $table->date('task_date');

            $table->string('time_range');

            $table->string('title');

            $table->longText('script')
                ->nullable();

            $table->longText('copy')
                ->nullable();

            $table->text('key_phrases')
                ->nullable();

            $table->string('youtube_url')
                ->nullable();

            $table->enum(
                'status',
                [
                    'pending',
                    'script_ready',
                    'editing',
                    'review',
                    'scheduled',
                    'published',
                    'cancelled',
                ]
            )->default('pending');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'task_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'video_tasks'
        );
    }
};