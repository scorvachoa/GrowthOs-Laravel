<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('youtube_channel_id');
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index(['task_date', 'time_range']);
            $table->index(['status', 'task_date']);
            $table->index(['status', 'updated_at']);
            $table->index('created_by');
            $table->index('channel_id');
        });

        Schema::table('extra_tasks', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index(['task_date', 'time_range']);
            $table->index(['task_date', 'time_range', 'title']);
            $table->index('created_by');
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('channel_id');
            $table->index(['channel_id', 'is_used', 'created_at']);
        });

        Schema::table('report_histories', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::table('generated_videos', function (Blueprint $table) {
            $table->index('organization_id');
            $table->index(['user_id', 'organization_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('organization_id');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id', 'created_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('generated_videos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'organization_id', 'created_at']);
            $table->dropIndex(['organization_id']);
        });

        Schema::table('report_histories', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['organization_id']);
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->dropIndex(['channel_id', 'is_used', 'created_at']);
            $table->dropIndex(['channel_id']);
            $table->dropIndex(['organization_id']);
        });

        Schema::table('extra_tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['task_date', 'time_range', 'title']);
            $table->dropIndex(['task_date', 'time_range']);
            $table->dropIndex(['organization_id']);
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->dropIndex(['channel_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['status', 'task_date']);
            $table->dropIndex(['task_date', 'time_range']);
            $table->dropIndex(['organization_id']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex(['youtube_channel_id']);
            $table->dropIndex(['organization_id']);
        });
    }
};
