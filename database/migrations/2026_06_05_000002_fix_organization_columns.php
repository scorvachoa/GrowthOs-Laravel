<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add organization_id to channels if missing
        if (!Schema::hasColumn('channels', 'organization_id')) {
            Schema::table('channels', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
        if (!Schema::hasColumn('video_tasks', 'organization_id')) {
            Schema::table('video_tasks', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
        if (!Schema::hasColumn('extra_tasks', 'organization_id')) {
            Schema::table('extra_tasks', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
        if (!Schema::hasColumn('ideas', 'organization_id')) {
            Schema::table('ideas', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        $defaultOrgId = DB::table('organizations')->value('id');

        // Populate channels.organization_id if any are still null
        if ($defaultOrgId) {
            DB::statement('UPDATE channels SET organization_id = ? WHERE organization_id IS NULL', [$defaultOrgId]);
        }

        // Populate video_tasks.organization_id if any are still null
        DB::statement('UPDATE video_tasks INNER JOIN users ON users.id = video_tasks.created_by SET video_tasks.organization_id = users.organization_id WHERE video_tasks.organization_id IS NULL');

        // Populate extra_tasks.organization_id if any are still null
        DB::statement('UPDATE extra_tasks INNER JOIN users ON users.id = extra_tasks.created_by SET extra_tasks.organization_id = users.organization_id WHERE extra_tasks.organization_id IS NULL');

        // Populate ideas.organization_id from their channel, then make NOT NULL
        DB::statement('UPDATE ideas INNER JOIN channels ON channels.id = ideas.channel_id SET ideas.organization_id = channels.organization_id WHERE ideas.organization_id IS NULL');
        Schema::table('ideas', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });

        // Add organization_id to report_histories
        Schema::table('report_histories', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
        DB::statement('UPDATE report_histories INNER JOIN users ON users.id = report_histories.user_id SET report_histories.organization_id = users.organization_id WHERE report_histories.organization_id IS NULL');
        Schema::table('report_histories', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });

        // Add user_id and organization_id to generated_videos
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
        $defaultUserId = DB::table('users')->orderBy('id')->value('id');
        if ($defaultUserId) {
            DB::statement('UPDATE generated_videos SET user_id = ? WHERE user_id IS NULL', [$defaultUserId]);
        }
        if ($defaultOrgId) {
            DB::statement('UPDATE generated_videos SET organization_id = ? WHERE organization_id IS NULL', [$defaultOrgId]);
        }
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('generated_videos', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'organization_id']);
        });
        Schema::table('report_histories', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};
