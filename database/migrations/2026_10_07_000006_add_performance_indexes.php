<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices compuestos para los filtros calientes de la app (org + fecha,
     * org + estado de borrador, org + fecha en sesiones) y FULLTEXT para
     * reemplazar los LIKE sobre script/copy/notes y ideas.content.
     *
     * Nota: roles(organization_id) NO se añade porque el unique
     * roles_org_name_guard_unique (organization_id, name, guard_name) ya lo
     * cubre como prefijo.
     */
    public function up(): void
    {
        Schema::table('video_tasks', function (Blueprint $table) {
            $table->index(['organization_id', 'task_date', 'time_range'], 'idx_video_tasks_org_date_range');
            $table->index(['organization_id', 'is_pending', 'task_date'], 'idx_video_tasks_org_pending_date');
        });

        Schema::table('extra_tasks', function (Blueprint $table) {
            $table->index(['organization_id', 'task_date'], 'idx_extra_tasks_org_date');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->index(['organization_id', 'date'], 'idx_work_sessions_org_date');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['causer_id', 'created_at'], 'idx_activity_log_causer_created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE video_tasks ADD FULLTEXT ft_video_tasks_search (title, script, copy, notes)');
            DB::statement('ALTER TABLE ideas ADD FULLTEXT ft_ideas_content (content)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE video_tasks DROP INDEX ft_video_tasks_search');
            DB::statement('ALTER TABLE ideas DROP INDEX ft_ideas_content');
        }

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_activity_log_causer_created_at');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_work_sessions_org_date');
        });

        Schema::table('extra_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_extra_tasks_org_date');
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_video_tasks_org_pending_date');
            $table->dropIndex('idx_video_tasks_org_date_range');
        });
    }
};
