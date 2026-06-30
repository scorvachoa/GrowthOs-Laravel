<?php

use App\Models\TimeOff;
use App\Models\Vacation;
use App\Models\WorkSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->index('organization_id');
        });

        Schema::table('time_off', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('status');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('status');
        });

        WorkSession::query()
            ->whereNull('organization_id')
            ->update(['organization_id' => DB::raw('(SELECT organization_id FROM video_tasks WHERE video_tasks.id = work_sessions.video_task_id)')]);

        TimeOff::query()
            ->whereNull('organization_id')
            ->update(['organization_id' => DB::raw('(SELECT organization_id FROM users WHERE users.id = time_off.user_id)')]);

        Vacation::query()
            ->whereNull('organization_id')
            ->update(['organization_id' => DB::raw('(SELECT organization_id FROM users WHERE users.id = vacations.user_id)')]);
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('time_off', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
