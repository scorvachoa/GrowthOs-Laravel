<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('video_tasks', 'title_ai_generated')) {
            Schema::table('video_tasks', function (Blueprint $table) {
                $table->dropColumn('title_ai_generated');
            });
        }

        if (Schema::hasColumn('video_tasks', 'copy_ai_generated')) {
            Schema::table('video_tasks', function (Blueprint $table) {
                $table->dropColumn('copy_ai_generated');
            });
        }

        if (Schema::hasColumn('video_task_translations', 'translated_with')) {
            Schema::table('video_task_translations', function (Blueprint $table) {
                $table->dropColumn('translated_with');
            });
        }
    }

    public function down(): void
    {
        Schema::table('video_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('video_tasks', 'title_ai_generated')) {
                $table->boolean('title_ai_generated')->default(false)->after('title');
            }
            if (! Schema::hasColumn('video_tasks', 'copy_ai_generated')) {
                $table->boolean('copy_ai_generated')->default(false)->after('copy');
            }
        });

        Schema::table('video_task_translations', function (Blueprint $table) {
            if (! Schema::hasColumn('video_task_translations', 'translated_with')) {
                $table->string('translated_with', 20)->nullable()->after('youtube_url');
            }
        });
    }
};
