<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_task_translations', function (Blueprint $table) {
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

        DB::table('video_tasks')->whereNotNull('translations')->orderBy('id')->chunk(200, function ($tasks) {
            $now = now();
            $rows = [];

            foreach ($tasks as $task) {
                $translations = json_decode($task->translations, true);

                if (! is_array($translations)) {
                    continue;
                }

                foreach ($translations as $locale => $fields) {
                    if (! is_array($fields)) {
                        continue;
                    }

                    $rows[] = [
                        'video_task_id' => $task->id,
                        'locale' => mb_substr((string) $locale, 0, 10),
                        'title' => $fields['title'] ?? null,
                        'script' => $fields['script'] ?? null,
                        'copy' => $fields['copy'] ?? null,
                        'youtube_url' => $fields['youtube_url'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('video_task_translations')->insert($rows);
            }
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }

    public function down(): void
    {
        Schema::table('video_tasks', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('copy');
        });

        $grouped = DB::table('video_task_translations')
            ->select('video_task_id', 'locale', 'title', 'script', 'copy', 'youtube_url')
            ->get()
            ->groupBy('video_task_id');

        foreach ($grouped as $taskId => $rows) {
            $translations = [];

            foreach ($rows as $row) {
                $translations[$row->locale] = [
                    'title' => $row->title,
                    'script' => $row->script,
                    'copy' => $row->copy,
                    'youtube_url' => $row->youtube_url,
                ];
            }

            DB::table('video_tasks')
                ->where('id', $taskId)
                ->update(['translations' => json_encode($translations, JSON_UNESCAPED_UNICODE)]);
        }

        Schema::dropIfExists('video_task_translations');
    }
};
