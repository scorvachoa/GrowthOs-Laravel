<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('video_task_translations')) {
            return;
        }

        $taskIds = DB::table('video_task_translations')
            ->orderBy('id')
            ->pluck('video_task_id')
            ->unique()
            ->values();

        foreach ($taskIds->chunk(50) as $chunk) {
            DB::transaction(function () use ($chunk) {
                $rowsByTask = DB::table('video_task_translations')
                    ->whereIn('video_task_id', $chunk->all())
                    ->orderBy('id')
                    ->get()
                    ->groupBy('video_task_id');

                foreach ($rowsByTask as $taskId => $rows) {
                    $source = DB::table('video_tasks')->where('id', $taskId)->first();

                    if (! $source) {
                        DB::table('video_task_translations')->where('video_task_id', $taskId)->delete();

                        continue;
                    }

                    $groupId = (string) Str::uuid();

                    DB::table('video_tasks')->where('id', $taskId)->update([
                        'translation_group_id' => $groupId,
                    ]);

                    foreach ($rows as $row) {
                        $channel = DB::table('channels')
                            ->where('organization_id', $source->organization_id)
                            ->where('locale', $row->locale)
                            ->orderBy('id')
                            ->first();

                        $newTaskId = DB::table('video_tasks')->insertGetId([
                            'task_date' => $source->task_date,
                            'time_range' => $source->time_range,
                            'title' => $row->title ?: $source->title,
                            'script' => $row->script ?? $source->script,
                            'copy' => $row->copy ?? $source->copy,
                            'youtube_url' => $row->youtube_url,
                            'status' => $row->youtube_url ? 'published' : 'pending',
                            'created_by' => $source->created_by,
                            'channel_id' => $channel?->id,
                            'key_phrases' => $source->key_phrases,
                            'organization_id' => $source->organization_id,
                            'is_pending' => $source->is_pending,
                            'translation_group_id' => $groupId,
                            'translated_from_task_id' => $source->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('work_sessions')
                            ->where('video_task_id', $source->id)
                            ->where('locale', $row->locale)
                            ->update(['video_task_id' => $newTaskId]);
                    }

                    // Se borran en la misma transacción: si algo falla, la tarea
                    // vuelve a procesarse en la siguiente ejecución sin duplicar.
                    DB::table('video_task_translations')->where('video_task_id', $taskId)->delete();
                }
            });
        }

        DB::table('video_task_translations')->truncate();
    }

    public function down(): void
    {
        // No se puede reconstruir la tabla a partir de tareas ya separadas.
    }
};
