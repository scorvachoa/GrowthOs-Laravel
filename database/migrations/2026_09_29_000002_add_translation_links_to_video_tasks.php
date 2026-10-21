<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('video_tasks', 'translation_group_id')) {
            Schema::table('video_tasks', function (Blueprint $table) {
                $table->string('translation_group_id', 36)->nullable()->index()->after('channel_id');
            });
        }

        if (! Schema::hasColumn('video_tasks', 'translated_from_task_id')) {
            Schema::table('video_tasks', function (Blueprint $table) {
                $table->foreignId('translated_from_task_id')
                    ->nullable()
                    ->constrained('video_tasks')
                    ->nullOnDelete()
                    ->after('translation_group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('video_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('video_tasks', 'translated_from_task_id')) {
                $table->dropConstrainedForeignId('translated_from_task_id');
            }
            if (Schema::hasColumn('video_tasks', 'translation_group_id')) {
                $table->dropColumn('translation_group_id');
            }
        });
    }
};
